<?php

declare(strict_types=1);

use App\Loader\EventLoader;
use App\Lock\RedisLockManager;
use App\Source\EventSourceCollection;
use App\Source\RestEventSource;
use App\Source\SoapEventSource;
use App\Source\FtpCsvEventSource;
use App\Store\MysqlEventStore;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Slim\App;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\RedisStore;

return function (App $app): void {

    $createLockManager = function (): RedisLockManager {
        $redis = new \Redis();
        $redis->connect(
            getenv('REDIS_HOST') ?: '127.0.0.1',
            (int) (getenv('REDIS_PORT') ?: 6379),
        );

        $redisStore = new RedisStore($redis);
        $lockFactory = new LockFactory($redisStore);
        
        return new RedisLockManager($lockFactory, $redis);
    };

    $app->get('/', function (Request $request, Response $response) {
        $html = <<<'HTML'
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Event Loader System</title>
            <style>
                body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; max-width: 800px; margin: 40px auto; padding: 0 20px; color: #333; line-height: 1.6; }
                h1 { border-bottom: 2px solid #eee; padding-bottom: 10px; }
                h2 { color: #555; margin-top: 30px; }
                code { background: #f4f4f4; padding: 2px 6px; border-radius: 3px; font-size: 0.9em; }
                pre { background: #f4f4f4; padding: 15px; border-radius: 5px; overflow-x: auto; }
                .run-btn { display: inline-block; margin: 20px 0; padding: 12px 24px; background: #2563eb; color: #fff; text-decoration: none; border-radius: 6px; font-weight: bold; }
                .run-btn:hover { background: #1d4ed8; }
                .stop-btn { display: inline-block; margin: 20px 0 20px 10px; padding: 12px 24px; background: #dc2626; color: #fff; text-decoration: none; border-radius: 6px; font-weight: bold; }
                .stop-btn:hover { background: #b91c1c; }
                .warn { background: #fef3c7; border-left: 4px solid #f59e0b; padding: 10px 15px; margin: 15px 0; }
                ul { padding-left: 20px; }
                li { margin-bottom: 8px; }
            </style>
        </head>
        <body>
            <h1>Event Loader System</h1>
            <p>A centralized event loading mechanism that collects events from multiple sources into a single storage.</p>

            <h2>Architecture</h2>
            <ul>
                <li><strong>EventSourceInterface</strong> — contract for fetching events from a remote source (REST, SOAP, FTP/CSV, etc.)</li>
                <li><strong>EventStoreInterface</strong> — contract for persisting events (MySQL implementation provided)</li>
                <li><strong>LockManagerInterface</strong> — contract for distributed locking (Redis implementation provided)</li>
                <li><strong>EventLoader</strong> — main coordinator that runs an infinite round-robin loop over all sources</li>
                <li><strong>EventSourceCollection</strong> — type-safe collection ensuring only <code>EventSourceInterface</code> instances are accepted</li>
            </ul>

            <h2>How It Works</h2>
            <ol>
                <li>The loader iterates over all configured event sources in a <strong>round-robin</strong> loop.</li>
                <li>For each source, it attempts to <strong>acquire a distributed lock</strong> via Redis.</li>
                <li>If the lock is acquired, it fetches new events (using <code>lastEventId</code> as cursor) and stores them.</li>
                <li>If the lock is held by another instance or the <strong>200ms cooldown</strong> hasn't elapsed — the source is skipped.</li>
                <li>On source failure, the error is logged and the loader moves to the next source.</li>
            </ol>

            <h2>Parallel Execution</h2>
            <p>Multiple loader instances can run simultaneously (even on different servers). Redis ensures:</p>
            <ul>
                <li><strong>No duplicate fetches</strong> — only one instance can work with a source at a time</li>
                <li><strong>200ms rate limit</strong> — minimum interval between requests to the same source</li>
                <li><strong>Crash safety</strong> — locks auto-expire after 30 seconds if the holder dies</li>
            </ul>

            <h2>Configured Sources</h2>
            <ul>
                <li><code>RestEventSource</code> — fetches events via REST API (JSON over HTTP)</li>
                <li><code>SoapEventSource</code> — fetches events via SOAP (XML over HTTP)</li>
                <li><code>FtpCsvEventSource</code> — downloads and parses a CSV file over FTP</li>
            </ul>

            <div class="warn">
                <strong>Requirements:</strong> The <code>/run</code> route requires a running Redis server and a MySQL database.
                Configure connection details via environment variables before starting.
            </div>

            <h2>Control the Loader</h2>
            <p>Navigate to <code>/run</code> to start the event loading process.</p>
            <p><strong>Warning:</strong> this runs an infinite loop and will not return a response until stopped.</p>
            <p>Use <code>/stop</code> to send a stop signal to all running loader instances.</p>
            <a href="/run" class="run-btn">Start Event Loader</a>
            <a href="/stop" class="stop-btn">Stop All Processes</a>

            <h2>Project Structure</h2>
            <pre>
            src/
            ├── DTO/
            │   └── EventDataDTO.php
            ├── Loader/
            │   └── EventLoader.php
            ├── Lock/
            │   ├── Contract/
            │   │   └── LockManagerInterface.php
            │   └── RedisLockManager.php
            ├── Routes/
            │   └── web.php
            ├── Source/
            │   ├── Contract/
            │   │   └── EventSourceInterface.php
            │   ├── Exception/
            │   │   └── SourceUnavailableException.php
            │   ├── EventSourceCollection.php
            │   ├── RestEventSource.php
            │   ├── SoapEventSource.php
            │   └── FtpCsvEventSource.php
            └── Store/
                ├── Contract/
                │   └── EventStoreInterface.php
                └── MysqlEventStore.php
            </pre>
        </body>
        </html>
        HTML;

        $response->getBody()->write($html);
        return $response;
    });

    $app->get('/run', function (Request $request, Response $response) use ($createLockManager) {
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        if (function_exists('apache_setenv')) {
            @apache_setenv('no-gzip', '1');
        }
        @ini_set('zlib.output_compression', '0');
        @ini_set('implicit_flush', '1');
        @ini_set('output_buffering', '0');
        
        header('Content-Type: text/html; charset=utf-8');
        header('X-Accel-Buffering: no');
        header('Cache-Control: no-cache');
        
        echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Event Loader - Live Logs</title>';
        echo '<style>';
        echo 'body { font-family: "Courier New", monospace; background: #1e1e1e; color: #d4d4d4; padding: 20px; margin: 0; }';
        echo 'h2 { color: #4ec9b0; border-bottom: 2px solid #4ec9b0; padding-bottom: 10px; }';
        echo '.log { margin: 3px 0; padding: 4px 8px; border-radius: 3px; }';
        echo '.EMERGENCY, .ALERT, .CRITICAL { background: #5a1e1e; color: #f48771; font-weight: bold; }';
        echo '.ERROR { color: #f48771; }';
        echo '.WARNING { color: #dcdcaa; }';
        echo '.INFO { color: #4ec9b0; }';
        echo '.DEBUG { color: #9cdcfe; }';
        echo '.NOTICE { color: #c586c0; }';
        echo '.timestamp { color: #858585; }';
        echo '.level { font-weight: bold; margin: 0 8px; }';
        echo '</style>';
        echo '</head><body>';
        echo '<h2>🔄 Event Loader - Live Logs</h2>';
        echo '<div id="logs">';
        echo str_repeat(' ', 1024);
        flush();
        
        $lockManager = $createLockManager();

        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=utf8mb4',
            getenv('DB_HOST') ?: '127.0.0.1',
            getenv('DB_NAME') ?: 'events',
        );
        $pdo = new \PDO($dsn, getenv('DB_USER') ?: 'root', getenv('DB_PASS') ?: '');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        $store = new MysqlEventStore($pdo);

        $sources = new EventSourceCollection(
            new RestEventSource('rest-api'),
            new SoapEventSource('soap-api'),
            new FtpCsvEventSource('ftp-csv'),
        );

        $logger = new class implements LoggerInterface {
            public function emergency(\Stringable|string $message, array $context = []): void { $this->log(LogLevel::EMERGENCY, $message, $context); }
            public function alert(\Stringable|string $message, array $context = []): void { $this->log(LogLevel::ALERT, $message, $context); }
            public function critical(\Stringable|string $message, array $context = []): void { $this->log(LogLevel::CRITICAL, $message, $context); }
            public function warning(\Stringable|string $message, array $context = []): void { $this->log(LogLevel::WARNING, $message, $context); }
            public function notice(\Stringable|string $message, array $context = []): void { $this->log(LogLevel::NOTICE, $message, $context); }
            public function info(\Stringable|string $message, array $context = []): void { $this->log(LogLevel::INFO, $message, $context); }
            public function debug(\Stringable|string $message, array $context = []): void { $this->log(LogLevel::DEBUG, $message, $context); }
            public function error(\Stringable|string $message, array $context = []): void { $this->log(LogLevel::ERROR, $message, $context); }

            public function log($level, \Stringable|string $message, array $context = []): void
            {
                $replace = [];
                foreach ($context as $key => $val) {
                    $replace['{' . $key . '}'] = $val;
                }
                $timestamp = date('Y-m-d H:i:s');
                $logMessage = strtr($message, $replace);
                
                $line = sprintf(
                    '<div class="log %s"><span class="timestamp">[%s]</span><span class="level">[%s]</span>%s</div>',
                    strtoupper($level),
                    $timestamp,
                    strtoupper($level),
                    htmlspecialchars($logMessage, ENT_QUOTES, 'UTF-8')
                );
                
                echo $line . "\n";
                
                $dockerLog = sprintf("[%s] [%s] %s", $timestamp, $level, $logMessage);
                error_log($dockerLog);
                
                flush();
            }
        };

        $loader = new EventLoader($sources, $store, $lockManager, $logger);
        $loader->run();

        echo '</div><div class="log NOTICE"><span class="timestamp">[' . date('Y-m-d H:i:s') . ']</span><span class="level">[NOTICE]</span>Loader stopped.</div>';
        echo '</body></html>';
        
        exit;
    });

    $app->get('/stop', function (Request $request, Response $response) use ($createLockManager) {
        $lockManager = $createLockManager();
        $lockManager->sendStopSignal();

        $response->getBody()->write(json_encode([
            'status' => 'success',
            'message' => 'Stop signal sent to all running loader instances.',
        ]));
        return $response->withHeader('Content-Type', 'application/json');
    });

};
