<?php
// ЛР5. Пользовательский обработчик ошибок для ZOOSHOP.
// Файл подключается в includes/config.php до работы с базой данных.

if (!defined('LAB5_ERROR_HANDLER_LOADED')) {
    define('LAB5_ERROR_HANDLER_LOADED', true);

    function lab5_error_type($errno)
    {
        $types = [
            E_ERROR => 'Фатальная ошибка',
            E_WARNING => 'Предупреждение',
            E_PARSE => 'Ошибка синтаксиса',
            E_NOTICE => 'Уведомление',
            E_CORE_ERROR => 'Фатальная ошибка ядра',
            E_CORE_WARNING => 'Предупреждение ядра',
            E_COMPILE_ERROR => 'Ошибка компиляции',
            E_COMPILE_WARNING => 'Предупреждение компиляции',
            E_USER_ERROR => 'Пользовательская фатальная ошибка',
            E_USER_WARNING => 'Пользовательское предупреждение',
            E_USER_NOTICE => 'Пользовательское уведомление',
            E_STRICT => 'Строгое предупреждение',
            E_RECOVERABLE_ERROR => 'Восстановимая ошибка',
            E_DEPRECATED => 'Устаревшая возможность',
            E_USER_DEPRECATED => 'Пользовательское предупреждение об устаревании',
        ];

        return $types[$errno] ?? 'Неизвестная ошибка';
    }

    function lab5_h($value)
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }

    function lab5_log_error($type, $message, $file, $line)
    {
        $logFile = __DIR__ . '/error_log.txt';
        $date = date('Y-m-d H:i:s');
        $record = "[$date] $type: $message in $file on line $line" . PHP_EOL;
        @file_put_contents($logFile, $record, FILE_APPEND | LOCK_EX);
    }

    function lab5_show_error_page($title, $message)
    {
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/html; charset=UTF-8');
        }

        echo '<!DOCTYPE html><html lang="ru"><head><meta charset="UTF-8"><title>Ошибка — ZOOSHOP</title>';
        echo '<style>body{font-family:Arial,sans-serif;background:#f3f8fb;color:#24343d;padding:40px}.box{max-width:760px;margin:auto;background:#fff;border-radius:18px;padding:26px;box-shadow:0 12px 32px rgba(32,82,110,.12);border-left:8px solid #ce3b3b}.btn{display:inline-block;margin-top:18px;background:#1d73a7;color:#fff;padding:10px 14px;border-radius:10px;text-decoration:none;font-weight:700}</style>';
        echo '</head><body><div class="box">';
        echo '<h1>' . lab5_h($title) . '</h1>';
        echo '<p>' . lab5_h($message) . '</p>';
        echo '<p>Информация об ошибке записана в журнал <strong>includes/error_log.txt</strong>.</p>';
        echo '<a class="btn" href="javascript:history.back()">Вернуться назад</a>';
        echo '</div></body></html>';
    }

    function lab5_handle_error($errno, $errstr, $errfile, $errline)
    {
        if (!(error_reporting() & $errno)) {
            return false;
        }

        $type = lab5_error_type($errno);
        lab5_log_error($type, $errstr, $errfile, $errline);

        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['lab5_runtime_errors'][] = $type . ': ' . $errstr;
        }

        // Пользовательская фатальная ошибка: демонстрируем именно пользовательский обработчик ошибок.
        if ($errno === E_USER_ERROR) {
            lab5_show_error_page('Пользовательская фатальная ошибка', 'Сработал пользовательский обработчик ошибок set_error_handler(). Ошибка записана в журнал.');
            exit;
        }

        // Нефатальные ошибки обрабатываем сами, чтобы пользователь видел аккуратное сообщение.
        if (in_array($errno, [E_WARNING, E_NOTICE, E_USER_WARNING, E_USER_NOTICE, E_DEPRECATED, E_USER_DEPRECATED], true)) {
            return true;
        }

        return false;
    }

    function lab5_trigger_user_fatal($message)
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0] ?? [];
        $file = $trace['file'] ?? __FILE__;
        $line = $trace['line'] ?? __LINE__;

        lab5_handle_error(E_USER_ERROR, $message, $file, $line);
    }

    set_error_handler('lab5_handle_error');

    set_exception_handler(function (Throwable $exception) {
        lab5_log_error('Исключение', $exception->getMessage(), $exception->getFile(), $exception->getLine());
        lab5_show_error_page('Произошла ошибка приложения', $exception->getMessage());
        exit;
    });

    register_shutdown_function(function () {
        $error = error_get_last();
        if ($error === null) {
            return;
        }

        $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
        if (in_array($error['type'], $fatalTypes, true)) {
            $type = lab5_error_type($error['type']);
            lab5_log_error($type, $error['message'], $error['file'], $error['line']);
            lab5_show_error_page('Фатальная ошибка', 'Работа страницы остановлена. Сработал пользовательский обработчик фатальных ошибок.');
        }
    });
}
?>
