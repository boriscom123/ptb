<?php

return [
    'confirm' => '🤖 <b>Task for the AI agent</b>. Run it?',
    'confirm_followup' => '🤖 <b>Follow-up to task #:id</b>. Run it?',
    'dropped' => 'Task cancelled.',
    'draft_expired' => 'This draft has expired — please send the task again.',
    'parent_active' => 'This task is still running — wait for the result.',
    'too_long' => 'The task is too long (maximum :max characters).',
    'stopping' => 'Stopping…',
    'not_revertable' => 'This task cannot be reverted.',
    'runner_unavailable' => 'The agent is unavailable: :error',
    'revert_failed' => 'Revert failed: :error',
    'reply_hint' => 'Reply to this message to refine the task.',
    'commit' => 'Commit <code>:commit</code> · :stat',
    'migrations' => 'Migrations applied: :count',
    'reverted_by' => 'Reverted by commit <code>:commit</code>',
    'interrupted' => 'Execution was interrupted (the runner restarted). Please send the task again.',

    'status' => [
        'pending' => '⏳ Task #:id is queued',
        'running' => '🛠 Task #:id is running…',
        'completed' => '✅ Task #:id is done',
        'failed' => '❌ Task #:id failed',
        'cancelled' => '⏹ Task #:id was stopped',
        'reverting' => '↩️ Task #:id is being reverted…',
        'reverted' => '↩️ Task #:id was reverted',
    ],

    'buttons' => [
        'run' => '▶️ Run',
        'cancel' => 'Cancel',
        'stop' => '⏹ Stop',
        'revert' => '↩️ Revert',
        'revert_yes' => 'Yes, revert',
        'revert_no' => 'No',
    ],
];
