ЛР5. Где в проекте показать обработку ошибок

1. includes/config.php
   Подключение к MySQL сделано через mysqli_connect().
   Ошибки подключения, создания БД, выбора БД и установки кодировки показываются через mysqli_connect_error() / mysqli_error().
   Пользовательский обработчик ошибок здесь НЕ используется.

2. includes/init_db.php
   Создание таблиц и начальное заполнение БД идут через mysqli_query().
   Если запрос не выполняется, выводится:
   "Ошибка выполнения запроса: " . mysqli_error($conn)
   Пользовательский обработчик ошибок здесь НЕ используется.

3. auth/register.php
   Реальный пример из формы регистрации.
   Блок добавления пользователя сделан по методичке:
   - mysqli_prepare();
   - mysqli_stmt_bind_param();
   - mysqli_stmt_execute();
   - при ошибках echo "Ошибка ...: " . mysqli_error($conn); exit();
   trigger_error() и пользовательский обработчик ошибок для ошибок БД не используются.

4. pages/error_demo.php?demo=db_error
   Демонстрационный пример для скриншота в отчет.
   Код построен как в методичке: prepare -> bind_param -> execute.
   Ошибка специально вызывается на этапе mysqli_stmt_execute(): логин demo уже существует в таблице users.
   На экране будет сообщение вида:
   "Ошибка выполнения запроса: Duplicate entry 'demo' for key ..."

5. includes/error_handler.php
   Пользовательский обработчик ошибок оставлен только для НЕ SQL-ошибок:
   - E_USER_WARNING;
   - E_USER_NOTICE;
   - E_USER_ERROR;
   - фатальные ошибки через register_shutdown_function().

6. pages/error_demo.php
   Для отчета есть разные типы ошибок:
   - ?demo=warning — пользовательское предупреждение E_USER_WARNING;
   - ?demo=notice — пользовательское уведомление E_USER_NOTICE;
   - ?demo=user_fatal — пользовательская фатальная ошибка E_USER_ERROR;
   - ?demo=db_error — ошибка запроса к БД через mysqli_error();
   - ?demo=fatal — обычная фатальная ошибка через register_shutdown_function().
