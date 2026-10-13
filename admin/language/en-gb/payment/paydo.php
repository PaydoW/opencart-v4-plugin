<?php

$_['heading_title'] = 'Paydo Payment Gateway';

$_['text_paydo'] = '<a href="https://paydo.com" target="_blank"><img src="/extension/paydo/admin/view/image/payment/paydo.png" style="max-width:130px; height:auto;"></a>';
$_['text_extension'] = 'Extensions';
$_['text_success'] = 'Success: You have modified Paydo settings!';
$_['text_edit'] = 'Edit Paydo Payment Gateway settings';
$_['text_methods_synced'] = 'Synced %d payment methods.';
$_['text_search_methods'] = 'Search payment methods...';
$_['text_no_methods'] = 'No payment methods loaded yet. Sync them from PayDo first.';
$_['text_syncing'] = 'Syncing...';

$_['entry_public_id'] = 'Public Key';
$_['entry_secret_key'] = 'Secret Key';
$_['entry_methods_mode'] = 'Separate payment methods';
$_['entry_hosted_page'] = 'PayDo Hosted Page';
$_['entry_project_id'] = 'Project ID';
$_['entry_jwt_token'] = 'JWT Token';
$_['entry_sync_methods'] = 'Sync payment methods';
$_['entry_enabled_methods'] = 'Enabled PayDo methods';
$_['entry_order_status_wait'] = 'Order Status for Pending Payment';
$_['entry_order_status_success'] = 'Order Status for Successful Payment';
$_['entry_order_status_error'] = 'Order Status for Failed Payment';
$_['entry_status'] = 'Status';
$_['entry_sort_order'] = 'Sort Order';
$_['entry_ipn_url'] = 'IPN URL';

$_['help_public_id'] = 'You can find this parameter in your Paydo project settings.';
$_['help_secret_key'] = 'You can find this parameter in your Paydo project settings.';
$_['help_methods_mode'] = 'Show every enabled PayDo method as a separate payment option during checkout.';
$_['help_hosted_page'] = 'Offer the general PayDo payment page. The paymentMethod field is omitted for this option.';
$_['help_project_id'] = 'Used to load the payment methods available for your PayDo application.';
$_['help_jwt_token'] = 'Bearer token used only to synchronize payment methods with PayDo.';
$_['help_sync_methods'] = 'Loads enabled methods from PayDo. Saving this form is still required after selecting methods.';
$_['help_enabled_methods'] = 'Only selected methods will be offered to customers. Synced methods are not enabled automatically.';
$_['help_ipn_url'] = 'Copy this URL and add it in Paydo dashboard under "IPN" settings.';

$_['button_sync_methods'] = 'Sync from PayDo';

$_['error_permission'] = 'Warning: You do not have permission to modify these settings!';
$_['error_public_id'] = 'This field is required.';
$_['error_secret_key'] = 'This field is required.';
$_['error_methods_credentials'] = 'Enter Project ID and JWT Token first.';
$_['error_methods_request'] = 'Could not connect to PayDo.';
$_['error_methods_response'] = 'PayDo returned an invalid response.';
$_['error_methods_empty'] = 'PayDo returned no enabled payment methods.';
$_['error_enabled_methods'] = 'Enable PayDo Hosted Page or select at least one PayDo payment method.';
$_['error_methods_authorization'] = 'PayDo rejected the JWT token. Check the token and try again.';
$_['error_methods_project'] = 'The specified PayDo project was not found.';
$_['error_methods_validation'] = 'PayDo could not validate the synchronization request.';
$_['error_methods_service'] = 'PayDo is temporarily unavailable. Please try again later.';
