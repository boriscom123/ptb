<?php

namespace Tests\Unit\Agent;

use App\Agent\TaskMessage;
use PHPUnit\Framework\TestCase;

class TaskMessageTest extends TestCase
{
    public function test_markdown_is_converted_to_telegram_html(): void
    {
        $markdown = "## Итог\n- **`LinksRule`** — ссылки\n- правило <script>\n\n```php\n\$a = 1;\n```";

        $this->assertSame(
            "<b>Итог</b>\n• <b><code>LinksRule</code></b> — ссылки\n• правило &lt;script&gt;\n\n<pre>\$a = 1;\n</pre>",
            TaskMessage::markdownToHtml($markdown),
        );
    }
}
