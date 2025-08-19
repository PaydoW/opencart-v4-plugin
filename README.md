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
- Set **Sort Order** if needed.  
- Set **Status → Enabled**.

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
* [PayDo Documentation](https://paydo.com/contacts-page-customer-support/)
* [Contact PayDo support](https://paydo.com/contacts-page-customer-support/)
  
**TIP**: When contacting support it will help us if you provide:

* WordPress and WooCommerce Version
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

