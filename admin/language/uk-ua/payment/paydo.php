<?php

$_['heading_title'] = 'Paydo Payment Gateway';

$_['text_paydo'] = '<a href="https://paydo.com" target="_blank"><img src="/extension/paydo/admin/view/image/payment/paydo.png" style="max-width:130px; height:auto;"></a>';
$_['text_extension'] = 'Розширення';
$_['text_success'] = 'Налаштування успішно збережено!';
$_['text_edit'] = 'Редагування Paydo Payment Gateway';
$_['text_methods_synced'] = 'Синхронізовано методів оплати: %d.';
$_['text_search_methods'] = 'Пошук методів оплати...';
$_['text_no_methods'] = 'Методи оплати ще не завантажено. Спочатку синхронізуйте їх із PayDo.';
$_['text_syncing'] = 'Синхронізація...';

$_['entry_public_id'] = 'Публічний ключ';
$_['entry_secret_key'] = 'Секретний ключ';
$_['entry_methods_mode'] = 'Окремі методи оплати';
$_['entry_hosted_page'] = 'Загальна платіжна сторінка PayDo';
$_['entry_project_id'] = 'ID проєкту';
$_['entry_jwt_token'] = 'JWT-токен';
$_['entry_sync_methods'] = 'Синхронізація методів оплати';
$_['entry_enabled_methods'] = 'Дозволені методи PayDo';
$_['entry_order_status_wait'] = 'Статус замовлення при очікуванні оплати';
$_['entry_order_status_success'] = 'Статус замовлення при успішній оплаті';
$_['entry_order_status_error'] = 'Статус замовлення при невдалій оплаті';
$_['entry_status'] = 'Статус';
$_['entry_sort_order'] = 'Порядок сортування';
$_['entry_ipn_url'] = 'IPN URL';

$_['help_public_id'] = 'Цей параметр можна знайти в налаштуваннях проєкту в особистому кабінеті Paydo.';
$_['help_secret_key'] = 'Цей параметр можна знайти в налаштуваннях проєкту в особистому кабінеті Paydo.';
$_['help_methods_mode'] = 'Показувати кожен дозволений метод PayDo як окремий варіант оплати під час оформлення замовлення.';
$_['help_hosted_page'] = 'Пропонувати загальну платіжну сторінку PayDo. Для цього варіанта поле paymentMethod не передається.';
$_['help_project_id'] = 'Використовується для завантаження методів оплати, доступних вашому застосунку PayDo.';
$_['help_jwt_token'] = 'Bearer-токен, який використовується лише для синхронізації методів оплати з PayDo.';
$_['help_sync_methods'] = 'Завантажує активні методи з PayDo. Після вибору методів форму все одно потрібно зберегти.';
$_['help_enabled_methods'] = 'Покупцям будуть показані лише позначені методи. Після синхронізації методи не вмикаються автоматично.';
$_['help_ipn_url'] = 'Скопіюйте цю URL-адресу та додайте її в налаштуваннях IPN на сайті Paydo.';

$_['button_sync_methods'] = 'Синхронізувати з PayDo';

$_['error_permission'] = 'У вас немає прав для керування цим модулем!';
$_['error_public_id'] = 'Поле є обов’язковим для заповнення.';
$_['error_secret_key'] = 'Поле є обов’язковим для заповнення.';
$_['error_methods_credentials'] = 'Спочатку вкажіть ID проєкту та JWT-токен.';
$_['error_methods_request'] = 'Не вдалося підключитися до PayDo.';
$_['error_methods_response'] = 'PayDo повернув некоректну відповідь.';
$_['error_methods_empty'] = 'PayDo не повернув жодного активного методу оплати.';
$_['error_enabled_methods'] = 'Увімкніть загальну платіжну сторінку PayDo або виберіть щонайменше один метод оплати PayDo.';
$_['error_methods_authorization'] = 'PayDo відхилив JWT-токен. Перевірте токен і повторіть спробу.';
$_['error_methods_project'] = 'Зазначений проєкт PayDo не знайдено.';
$_['error_methods_validation'] = 'PayDo не зміг перевірити запит синхронізації.';
$_['error_methods_service'] = 'PayDo тимчасово недоступний. Спробуйте пізніше.';
