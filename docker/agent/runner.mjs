// Исполнитель задач AI-агента. Принимает задачи от Laravel по HTTP, выполняет их Claude Code
// в отдельном git worktree, прогоняет проверки, коммитит в рабочую ветку и сообщает результат.
// Если Laravel не смог принять результат после коммита (приложение сломано), коммит откатывается.

import { spawn } from 'node:child_process'
import { randomBytes, timingSafeEqual } from 'node:crypto'
import { existsSync } from 'node:fs'
import { rm, writeFile } from 'node:fs/promises'
import { createServer } from 'node:http'

const config = {
  port: 8080,
  secret: process.env.AGENT_SECRET ?? '',
  eventsUrl: process.env.EVENTS_URL,
  workspace: process.env.WORKSPACE ?? '/workspace',
  // Постоянный путь: сессии Claude Code привязаны к каталогу, так работает --resume для доработок
  worktree: process.env.WORKTREE ?? '/worktrees/current',
  branch: process.env.BRANCH ?? 'dev',
  taskTimeoutMs: Number(process.env.TASK_TIMEOUT_MINUTES ?? 30) * 60_000,
  fixAttempts: Number(process.env.FIX_ATTEMPTS ?? 2),
}

if (config.secret.length < 32 || !config.eventsUrl) {
  console.error('AGENT_SECRET (min 32 chars) and EVENTS_URL are required')
  process.exit(1)
}

// Окружение для проверок: свои ключи, без секретов приложения
const checkEnv = {
  ...process.env,
  APP_KEY: `base64:${randomBytes(32).toString('base64')}`,
  JWT_SECRET: randomBytes(48).toString('base64'),
}

const SYSTEM_PROMPT = `Ты работаешь автономно: задачу прислал администратор проекта через Telegram-бота, задать ему уточняющие вопросы нельзя — принимай разумные решения сам.
Правила проекта — в CLAUDE.md, следуй им. Не делай git commit/push/reset/checkout — коммит сделает исполнитель после проверок.
Перед завершением сам прогони проверки из CLAUDE.md (раздел «Проверки»).
Финальный ответ — краткий отчёт на русском для администратора (что сделано, что важно знать), без markdown-таблиц, до 15 строк.
Если задача — вопрос, а не изменение кода, просто ответь на него, ничего не меняя.`

const log = (...args) => console.log(new Date().toISOString(), ...args)

// --- Процессы ---------------------------------------------------------------

function run(command, args, { cwd = config.workspace, env = checkEnv, timeoutMs = 10 * 60_000, onSpawn } = {}) {
  return new Promise((resolve) => {
    const child = spawn(command, args, { cwd, env, detached: true })
    onSpawn?.(child)

    let output = ''
    let stdout = ''
    const collect = (chunk) => {
      output += chunk
      if (output.length > 2_000_000) output = output.slice(-1_000_000)
    }
    child.stdout.on('data', (chunk) => {
      stdout += chunk
      collect(chunk)
    })
    child.stderr.on('data', collect)

    const timer = setTimeout(() => kill(child), timeoutMs)
    child.on('close', (code, signal) => {
      clearTimeout(timer)
      resolve({ ok: code === 0, code, signal, output, stdout })
    })
    child.on('error', (error) => {
      clearTimeout(timer)
      resolve({ ok: false, code: -1, output: String(error), stdout: '' })
    })
  })
}

function kill(child) {
  try {
    process.kill(-child.pid, 'SIGTERM')
  } catch {}
}

async function git(args, cwd = config.workspace) {
  const result = await run('git', args, { cwd, env: process.env })
  if (!result.ok) throw new Error(`git ${args.join(' ')}: ${result.output.trim()}`)
  return result.output.trim()
}

const tail = (text, length = 3000) => (text.length > length ? '…' + text.slice(-length) : text).trim()

// --- Связь с Laravel ----------------------------------------------------------

async function report(taskId, payload, attempts = 1) {
  for (let attempt = 1; attempt <= attempts; attempt++) {
    try {
      const response = await fetch(config.eventsUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-Agent-Secret': config.secret },
        body: JSON.stringify({ task_id: taskId, ...payload }),
        signal: AbortSignal.timeout(120_000),
      })
      if (response.ok) return true
      log(`report #${taskId} ${payload.status}: HTTP ${response.status}`, tail(await response.text(), 500))
    } catch (error) {
      log(`report #${taskId} ${payload.status}: ${error.message}`)
    }
    if (attempt < attempts) await new Promise((resolve) => setTimeout(resolve, 5000))
  }
  return false
}

// --- Очередь ------------------------------------------------------------------

const queue = []
let current = null // { task, child, cancelled }
let chain = Promise.resolve()

// Все операции с репозиторием выполняются строго по одной
const serial = (fn) => (chain = chain.then(fn, fn))

function enqueue(task) {
  queue.push(task)
  serial(processNext)
}

async function processNext() {
  const task = queue.shift()
  if (!task) return

  current = { task, child: null, cancelled: false }
  try {
    await runTask(current)
  } catch (error) {
    log(`task #${task.id} crashed`, error)
    await report(task.id, { status: 'failed', error: tail(String(error.message ?? error)) }, 3)
  } finally {
    await cleanupWorktree().catch((error) => log('cleanup failed', error.message))
    current = null
  }
}

// --- Выполнение задачи --------------------------------------------------------

async function claude(state, prompt, sessionId) {
  const args = ['-p', prompt, '--output-format', 'json', '--dangerously-skip-permissions', '--max-turns', '100', '--append-system-prompt', SYSTEM_PROMPT]
  if (sessionId) args.push('--resume', sessionId)

  const result = await run('claude', args, {
    cwd: config.worktree,
    env: checkEnv,
    timeoutMs: config.taskTimeoutMs,
    onSpawn: (child) => (state.child = child),
  })
  state.child = null

  if (state.cancelled) throw new Cancelled()

  let parsed
  try {
    parsed = JSON.parse(result.stdout)
  } catch {
    throw new Error(`Claude Code: ${tail(result.output, 1500) || `exit ${result.code} ${result.signal ?? ''}`}`)
  }
  if (parsed.is_error) throw new Error(`Claude Code: ${parsed.result ?? parsed.subtype}`)

  return { summary: String(parsed.result ?? '').trim(), sessionId: parsed.session_id }
}

class Cancelled extends Error {}

const CHECKS = [
  ['PHP tests', 'php', ['artisan', 'test', '--compact'], 'backend'],
  ['PHP code style (pint)', 'vendor/bin/pint', ['--test'], 'backend'],
  ['Frontend tests', 'npm', ['test'], 'frontend'],
  ['Frontend build', 'npx', ['vite', 'build', '--outDir', '/tmp/agent-build', '--emptyOutDir'], 'frontend'],
]

async function checks(state) {
  for (const [name, command, args, dir] of CHECKS) {
    const result = await run(command, args, { cwd: `${config.worktree}/${dir}`, onSpawn: (child) => (state.child = child) })
    state.child = null
    if (state.cancelled) throw new Cancelled()
    if (!result.ok) return { ok: false, name, output: tail(result.output) }
  }
  return { ok: true }
}

async function prepareWorktree(base) {
  await cleanupWorktree()
  await git(['worktree', 'add', '-B', 'agent/current', config.worktree, base])

  // Зависимости копируются (не симлинки): автозагрузка Composer резолвит реальные пути
  for (const dir of ['backend/vendor', 'frontend/node_modules']) {
    const source = `${config.workspace}/${dir}`
    if (existsSync(source)) {
      const result = await run('cp', ['-a', source, `${config.worktree}/${dir}`], { env: process.env })
      if (!result.ok) throw new Error(`copy ${dir}: ${result.output}`)
    }
  }
  await writeFile(`${config.worktree}/backend/.env`, '')
}

async function cleanupWorktree() {
  if (existsSync(config.worktree)) {
    await run('git', ['worktree', 'remove', '--force', config.worktree], { env: process.env })
    await rm(config.worktree, { recursive: true, force: true })
  }
  await run('git', ['worktree', 'prune'], { env: process.env })
}

async function runTask(state) {
  const { task } = state
  log(`task #${task.id} started`)
  await report(task.id, { status: 'running' })

  try {
    const currentBranch = await git(['symbolic-ref', '--short', 'HEAD'])
    if (currentBranch !== config.branch) throw new Error(`Рабочая копия на ветке ${currentBranch}, ожидается ${config.branch}`)

    const base = await git(['rev-parse', config.branch])
    await prepareWorktree(base)

    let { summary, sessionId } = await claude(state, task.prompt, task.session_id)

    if ((await git(['status', '--porcelain'], config.worktree)) === '') {
      log(`task #${task.id} finished without changes`)
      await report(task.id, { status: 'completed', summary, session_id: sessionId }, 3)
      return
    }

    let result = await checks(state)
    for (let attempt = 1; !result.ok && attempt <= config.fixAttempts; attempt++) {
      log(`task #${task.id} checks failed (${result.name}), fix attempt ${attempt}`)
      ;({ summary, sessionId } = await claude(
        state,
        `Проверка «${result.name}» не прошла. Исправь ошибки, не ломая задачу. Вывод:\n\n${result.output}`,
        sessionId,
      ))
      result = await checks(state)
    }

    if (!result.ok) {
      await report(task.id, { status: 'failed', summary, session_id: sessionId, error: `Проверка «${result.name}» не прошла:\n${result.output}` }, 3)
      return
    }

    const commit = await commitAndMerge(task, summary, base)
    const changes = await describeChanges(base, commit)

    // Laravel обрабатывает результат уже новым кодом (миграции, сообщение в Telegram).
    // Не ответил — значит изменения сломали приложение: откатываем.
    const accepted = await report(task.id, { status: 'completed', summary, session_id: sessionId, commit, ...changes }, 3)

    if (!accepted) {
      log(`task #${task.id}: application did not accept the result, reverting ${commit}`)
      await revert(commit)
      await report(task.id, {
        status: 'failed',
        summary,
        session_id: sessionId,
        commit,
        reverted: true,
        error: 'После изменений приложение перестало отвечать — коммит автоматически откачен.',
      }, 5)
    }
  } catch (error) {
    if (error instanceof Cancelled) {
      log(`task #${task.id} cancelled`)
      await report(task.id, { status: 'cancelled' }, 3)
      return
    }
    throw error
  }
}

async function commitAndMerge(task, summary, base) {
  const title = task.prompt.split('\n')[0].slice(0, 60)
  const message = `agent: ${title}\n\n${summary.slice(0, 2000)}\n\nЗадача #${task.id}\n\nCo-Authored-By: Claude <noreply@anthropic.com>`

  await git(['add', '-A'], config.worktree)
  await git(['commit', '-q', '-m', message], config.worktree)

  // Пока агент работал, ветка могла уйти вперёд — переносим коммит поверх
  if ((await git(['rev-parse', config.branch])) !== base) {
    const rebase = await run('git', ['rebase', config.branch], { cwd: config.worktree, env: process.env })
    if (!rebase.ok) {
      await run('git', ['rebase', '--abort'], { cwd: config.worktree, env: process.env })
      throw new Error(`Ветка ${config.branch} изменилась во время работы агента, изменения конфликтуют:\n${tail(rebase.output, 1000)}`)
    }
  }

  await git(['merge', '--ff-only', '-q', 'agent/current'])
  const commit = await git(['rev-parse', 'HEAD'])
  await installDependencies(base, commit)

  return commit
}

async function describeChanges(from, to) {
  const files = (await git(['diff', '--name-status', from, to])).split('\n').filter(Boolean)
  const migrations = (await git(['diff', '--name-only', '--diff-filter=A', from, to, '--', 'backend/database/migrations']))
    .split('\n')
    .filter(Boolean)
  const stat = await git(['diff', '--shortstat', from, to])

  return { files, migrations, stat }
}

// Если изменились lock-файлы — обновляем зависимости рабочей копии
async function installDependencies(from, to) {
  const changed = await git(['diff', '--name-only', from, to])
  if (changed.includes('backend/composer.lock')) {
    await run('composer', ['install', '--no-interaction'], { cwd: `${config.workspace}/backend` })
  }
  if (changed.includes('frontend/package-lock.json')) {
    await run('npm', ['install'], { cwd: `${config.workspace}/frontend` })
  }
}

async function revert(commit) {
  const ancestor = await run('git', ['merge-base', '--is-ancestor', commit, 'HEAD'], { env: process.env })
  if (!ancestor.ok) throw new Error(`Коммит ${commit} не найден в ветке ${config.branch}`)

  const before = await git(['rev-parse', 'HEAD'])
  await git(['revert', '--no-edit', commit])
  const after = await git(['rev-parse', 'HEAD'])
  await installDependencies(before, after)

  return after
}

// --- HTTP API (доступен только Laravel через внутренний адрес Caddy) -------------

function authorized(request) {
  const given = Buffer.from(String(request.headers['x-agent-secret'] ?? ''))
  const expected = Buffer.from(config.secret)
  return given.length === expected.length && timingSafeEqual(given, expected)
}

async function body(request) {
  let data = ''
  for await (const chunk of request) {
    data += chunk
    if (data.length > 100_000) throw new Error('Body too large')
  }
  return data ? JSON.parse(data) : {}
}

function send(response, status, payload) {
  response.writeHead(status, { 'Content-Type': 'application/json' })
  response.end(JSON.stringify(payload))
}

const routes = {
  'GET /health': () => [200, { busy: current?.task.id ?? null, queued: queue.map((task) => task.id) }],

  'POST /tasks': async (data) => {
    if (!Number.isInteger(data.id) || typeof data.prompt !== 'string' || data.prompt.trim() === '') {
      return [422, { error: 'id and prompt are required' }]
    }
    if (current?.task.id === data.id || queue.some((task) => task.id === data.id)) return [409, { error: 'Task already queued' }]

    enqueue({ id: data.id, prompt: data.prompt, session_id: data.session_id ?? null })
    return [202, { position: queue.length }]
  },

  'POST /cancel': async (data) => {
    const index = queue.findIndex((task) => task.id === data.id)
    if (index >= 0) {
      queue.splice(index, 1)
      await report(data.id, { status: 'cancelled' }, 3)
      return [200, { cancelled: true }]
    }
    if (current?.task.id === data.id) {
      current.cancelled = true
      if (current.child) kill(current.child)
      return [200, { cancelled: true }]
    }
    return [404, { error: 'Task is not running' }]
  },

  'POST /revert': (data) =>
    new Promise((resolve) => {
      serial(async () => {
        try {
          resolve([200, { commit: await revert(String(data.commit ?? '')) }])
        } catch (error) {
          resolve([409, { error: error.message }])
        }
      })
    }),
}

createServer(async (request, response) => {
  const route = routes[`${request.method} ${request.url}`]
  if (!route) return send(response, 404, { error: 'Not found' })
  if (!authorized(request)) return send(response, 401, { error: 'Unauthorized' })

  try {
    const [status, payload] = await route(await body(request))
    send(response, status, payload)
  } catch (error) {
    send(response, 400, { error: error.message })
  }
}).listen(config.port, () => log(`agent runner listening on :${config.port}`))
