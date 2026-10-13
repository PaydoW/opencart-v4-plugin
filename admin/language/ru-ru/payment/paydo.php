<?php

$_['heading_title'] = 'Paydo Payment Gateway';

$_['text_paydo'] = '<a href="https://paydo.com" target="_blank"><img src="/extension/paydo/admin/view/image/payment/paydo.png" style="max-width:130px; height:auto;"></a>';
$_['text_extension'] = 'Расширения';
$_['text_success'] = 'Настройки успешно сохранены!';
$_['text_edit'] = 'Редактирование Paydo Payment Gateway';
$_['text_methods_synced'] = 'Синхронизировано методов оплаты: %d.';
$_['text_search_methods'] = 'Поиск методов оплаты...';
$_['text_no_methods'] = 'Методы оплаты ещё не загружены. Сначала синхронизируйте их с PayDo.';
$_['text_syncing'] = 'Синхронизация...';

$_['entry_public_id'] = 'Публичный ключ';
$_['entry_secret_key'] = 'Секретный ключ';
$_['entry_methods_mode'] = 'Отдельные методы оплаты';
$_['entry_hosted_page'] = 'Общая платёжная страница PayDo';
$_['entry_project_id'] = 'ID проекта';
$_['entry_jwt_token'] = 'JWT-токен';
$_['entry_sync_methods'] = 'Синхронизация методов оплаты';
$_['entry_enabled_methods'] = 'Разрешённые методы PayDo';
$_['entry_order_status_wait'] = 'Статус заказа при ожидании оплаты';
$_['entry_order_status_success'] = 'Статус заказа при успешной оплате';
$_['entry_order_status_error'] = 'Статус заказа при неудачной оплате';
$_['entry_status'] = 'Статус';
$_['entry_sort_order'] = 'Порядок сортировки';
$_['entry_ipn_url'] = 'IPN URL';

$_['help_public_id'] = 'Этот параметр можно найти в настройках проекта в личном кабинете Paydo.';
$_['help_secret_key'] = 'Этот параметр можно найти в настройках проекта в личном кабинете Paydo.';
$_['help_methods_mode'] = 'Показывать каждый разрешённый метод PayDo как отдельный вариант оплаты на странице оформления заказа.';
$_['help_hosted_page'] = 'Предлагать общую платёжную страницу PayDo. Для этого варианта поле paymentMethod не передаётся.';
$_['help_project_id'] = 'Используется для загрузки методов оплаты, доступных вашему приложению PayDo.';
$_['help_jwt_token'] = 'Bearer-токен, используемый только для синхронизации методов оплаты с PayDo.';
$_['help_sync_methods'] = 'Загружает активные методы из PayDo. После выбора методов форму всё равно нужно сохранить.';
$_['help_enabled_methods'] = 'Покупателям будут показаны только отмеченные методы. После синхронизации методы не включаются автоматически.';
$_['help_ipn_url'] = 'Скопируйте этот URL и добавьте его в настройках IPN на сайте Paydo.';

$_['button_sync_methods'] = 'Синхронизировать с PayDo';

$_['error_permission'] = 'У вас нет прав для управления этим модулем!';
$_['error_public_id'] = 'Поле обязательно для заполнения.';
$_['error_secret_key'] = 'Поле обязательно для заполнения.';
$_['error_methods_credentials'] = 'Сначала укажите ID проекта и JWT-токен.';
$_['error_methods_request'] = 'Не удалось подключиться к PayDo.';
$_['error_methods_response'] = 'PayDo вернул некорректный ответ.';
$_['error_methods_empty'] = 'PayDo не вернул ни одного активного метода оплаты.';
$_['error_enabled_methods'] = 'Включите общую платёжную страницу PayDo или выберите хотя бы один метод оплаты PayDo.';
$_['error_methods_authorization'] = 'PayDo отклонил JWT-токен. Проверьте токен и повторите попытку.';
$_['error_methods_project'] = 'Указанный проект PayDo не найден.';
$_['error_methods_validation'] = 'PayDo не смог проверить запрос синхронизации.';
$_['error_methods_service'] = 'PayDo временно недоступен. Повторите попытку позже.';
