# Paydo Payment Gateway for OpenCart v4

Accept online payments in OpenCart 4 via Paydo.com.

## Requirements
- OpenCart **4.0+**

---

## Installation and Setup (via OpenCart Admin Panel)

### 1) Upload the archive
1. Go to the [latest release](https://github.com/PaydoW/opencart-v4-plugin/releases).
2. Download the latest **`paydo.ocmod.zip`** file.
3. In your OpenCart admin panel go to **Extensions → Installer**.
4. Click **Upload** and select the `paydo.ocmod.zip` file.
5. Wait until you see the success message.

### 2) Install the extension
1. On the same **Installer** page, in the list of **Installed Extensions**, find **Paydo Payment Gateway Extension**.
2. Click the **green “+” (Install)** button to install it into the system.

### 3) Add the payment method
1. Go to **Extensions → Extensions**.
2. In the dropdown, choose **Payments**.
3. Find **Paydo Payment Gateway** in the list.
4. Click the **green “+” (Install)** button to add it to your payment methods.
5. Then click the **blue pencil (Edit)** button to configure the settings.

### 4) Configure the module
In the settings form, fill in:
- **Public Key** — your Paydo project Public Key.  
- **Secret Key** — your Paydo project Secret Key.  
- **IPN URL** — automatically generated link. Copy this link for use in Paydo dashboard.  
- Configure **Order Status for Pending/Successful/Failed payments**.  
- To let customers choose a specific PayDo payment method, enable **Separate payment methods** and configure the method list as described below.
- Set **Sort Order** if needed.  
- Set **Status → Enabled**.

### Optional: separate PayDo payment methods

This mode follows the PayDo method-selection flow used by the WooCommerce plugin:

1. Enable **Separate payment methods**.
2. Keep **PayDo Hosted Page** enabled if customers should also be able to choose their payment method on the general PayDo page.
3. Enter the PayDo **Project ID** and **JWT Token**.
4. Click **Sync from PayDo**.
5. Select the methods that should be available to customers.
6. Save the extension settings.

Every selected method is displayed as a separate option under PayDo during checkout. The selected method identifier is validated against the synchronized allowlist and sent to the PayDo v3 Invoice API in the `paymentMethod` field.

Method logos from PayDo appear beside their names in the standard OpenCart checkout picker. After updating the extension, open its admin settings once to register the checkout display event. Methods without a logo remain available with their name; a failed image download does not affect payment.

- **PayDo Hosted Page** creates an invoice without the `paymentMethod` field.
- A synchronized direct method creates an invoice with its positive numeric ID as `paymentMethod`.
- If method selection is disabled, checkout uses the legacy Hosted Page flow without requiring new settings.
- Project ID and JWT are used only to synchronize methods. JWT is never sent when an invoice is created or read.

At least one checkout option must remain enabled while method selection is active: Hosted Page, a synchronized direct method, or both.

### Updating from an earlier version

1. Back up the existing `extension/paydo` directory and database.
2. Upload and install the new `paydo.ocmod.zip` through **Extensions → Installer**.
3. Open the PayDo payment settings once. Version 2.0.0 adds missing settings and upgrades the existing invoice table without resetting Public Key, Secret Key, statuses, or other saved configuration.
4. Refresh modifications if your store or theme requires it.

Existing stores remain in the standard Hosted Page mode until **Separate payment methods** is explicitly enabled.

The release version is fixed at **2.0.0** throughout development and testing. Do not increment it for intermediate builds.

### Card payments (Internet acquiring)

Synchronize the methods, select the card/acquiring method enabled for your PayDo project, and save. Its numeric identifier is loaded from PayDo rather than hardcoded. Checkout opens the PayDo payment page for that specific method, where the customer enters card details and completes any required 3DS authentication.

The same IPN URL handles both card payments and alternative methods. Transaction identifiers may arrive in `invoice.txid`, `transaction.txid`, `transaction.id`, or `transaction.transactionIdentifier`; they are checked against the invoice returned by API v3.

### Payment confirmation and retries

The customer return URL does not mark an order as paid. PayDo IPN is matched to the locally stored invoice and verified with `GET /v3/invoices/{invoiceId}` before the order status changes. Invoice ID, order ID, amount, currency, and transaction identifiers are checked. Temporary verification errors leave the order pending and use bounded retries; terminal failure states are applied only after successful server-side invoice verification.

Pending invoice verification responds with HTTP 503 and `Retry-After: 60` so PayDo can redeliver the notification. Verified paid/failed notifications respond with HTTP 200. Duplicate notifications do not repeat order history, and late failure notifications cannot undo a verified payment.

### 5) Refresh modifications
Go to **Extensions → Modifications** and click **Refresh** to apply changes.

---

## How to get Public/Secret Keys and configure IPN in Paydo

### Public/Secret Keys
1. Log in to your account on **Paydo.com**.  
2. Go to **Overview → Project (website) details**.  
3. Open the **General information** tab.  
4. Copy your **Public Key** and **Secret Key** and paste them into the module settings in OpenCart.

### IPN (payment notifications)
1. In your Paydo dashboard go to **IPN settings**.  
2. Click **Add new IPN**.  
3. Paste the **IPN URL** from the module settings in OpenCart.  
4. Save.  

> **Note:** Without correct IPN setup your OpenCart store will not automatically receive payment status updates.

---

## Support

* [Open an issue](https://github.com/PaydoW/opencart-v4-plugin/issues) if you are having issues with this plugin.
* [PayDo Documentation](https://github.com/PaydoW/paydo-api-doc)
* [Contact PayDo support](https://paydo.com/contacts-page-customer-support/)
  
**TIP**: When contacting support it will help us if you provide:

* OpenCart and PHP versions
* Other plugins you have installed
  * Some plugins do not play nice
* Configuration settings for the plugin (Most merchants take screenshots)
* Any log files that will help
  * Web server error logs
* Screenshots of error message if applicable.

## Contribute

Would you like to help with this project?  Great!  You don't have to be a developer, either.
If you've found a bug or have an idea for an improvement, please open an
[issue](https://github.com/PaydoW/opencart-v4-plugin/issues) and tell us about it.

If you *are* a developer wanting contribute an enhancement, bugfix or other patch to this project,
please fork this repository and submit a pull request detailing your changes.  We review all PRs!

This open source project is released under the [MIT license](http://opensource.org/licenses/MIT)
which means if you would like to use this project's code in your own project you are free to do so.


## License

Please refer to the [LICENSE](https://github.com/PaydoW/opencart-v4-plugin/blob/master/LICENSE) file that came with this project.
