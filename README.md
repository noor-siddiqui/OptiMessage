<center>
<img src="OptiMessage.png" alt="OptiMessage" width="350" alt="OptiMessage Logo">
</center>

<p align="center">
  <strong>A Powerful, Open-Source SMS Notification and Bulk Marketing Framework for WooCommerce.</strong>
</p>

## 🚀 Overview

**OptiMessage** integrates your WooCommerce store with Twilio to ensure your customers get real-time order updates via SMS, while empowering you with targeted, consent-based bulk SMS marketing tools that filter previous purchases effortlessly.

## 🌟 Key Features

- **Automated Order Triggers:** Natively sends customized SMS messages automatically when WooCommerce Order statuses change (Processing, Completed, Refunded).
- **Universal Checkout Support:** Effortlessly supports both the **Modern WooCommerce Blocks** checkout API and the classic WooCommerce Shortcode checkouts.
- **Smart SMS Consent & Privacy:** Injects a "Receive SMS Notifications" opt-in checkbox directly into your checkout and user profile pages. Bulk campaigns respect these consent choices automatically!
- **Dynamic Template Tags:** Personalize every SMS using tags like `{first_name}`, `{last_name}`, `{order_id}`, `{total}`, `{tracking_number}`, and `{tracking_url}`.
- **Twilio Number Verification:** Validates raw numbers via Twilio’s Lookup API, auto-correcting local numbers (e.g., automatically determining formatting like `+1` or `+880` based on the billing country).
- **Consent-Driven Bulk SMS:** Send targeted texts based on the exact Products your customers have bought—the framework calculates audience size securely based on those who chose to opt-in.
- **Built-in History & Webhooks:** Tracks SMS statuses instantly inside the WP Admin via native Twilio `StatusCallback` webhooks (`Sent`, `Delivered`, `Failed`).

---

## 🛠️ Installation

Because OptiMessage is updated directly through GitHub, you can securely install it as follows:

1. Click **Code > Download ZIP** at the top right of this page.
2. Log into your WordPress admin dashboard.
3. Go to **Plugins > Add New Plugin** and click **Upload Plugin**.
4. Choose the downloaded `.zip` file and click `Install Now`, then activate the plugin.

*(Note: Thanks to the integrated Plugin Update Checker, all future releases published on this GitHub repository will show up natively inside your WordPress Updates dashboard!)*

## ⚙️ Configuration

1. Navigate to **OptiMessage > Twilio API** on your WordPress dashboard.
2. Paste your Twilio Account **SID**, **Auth Token**, and your **Twilio Phone Number** (or Messaging Service SID starting with `MG...`).
3. Set up your global notification checkboxes and templates inside the **General Settings** tab.
4. Ensure the **Make Consent Required** setting is checked if privacy regulations dictate strict SMS consent locally!

## 🧪 Testing Webhooks on Localhost

Please note that Twilio cannot send Webhooks (Delivery Receipts) back to `.local` or `localhost` environments. To test the SMS History's delivery callbacks effectively, test the webhook functionality on a publicly accessible WordPress staging or production environment.

## 🤝 Contributing

Contributions are what make the open-source community such an amazing place to learn, inspire, and create. Any contributions you make are **greatly appreciated**.

1. Fork the Project
2. Create your Feature Branch (`git checkout -b feature/AmazingFeature`)
3. Commit your Changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the Branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

## 🔒 Security & Vulnerabilities

If you discover any security-related issues or vulnerabilities within OptiMessage, please do not disclose them publicly via GitHub issues. Instead, please email the author directly or open a confidential security advisory on GitHub. All security vulnerabilities will be promptly addressed.

## 👨‍💻 Developer

**Author:** Noor Nabiul Alam Siddiqui
**Email:**  [siddiqui.sazal@gmail.com](mailto:siddiqui.sazal@gmail.com)
