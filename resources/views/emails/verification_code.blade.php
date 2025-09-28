<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Update Verification Code</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f8fafc;
            color: #222;
            margin: 0;
            padding: 0;
        }
        .email-container {
            max-width: 480px;
            margin: 40px auto;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 4px 24px rgba(102, 126, 234, 0.08);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
            padding: 32px 0 18px 0;
            text-align: center;
        }
        .logo {
            font-size: 28px;
            font-weight: 700;
            letter-spacing: 1px;
            margin-bottom: 6px;
        }
        .subtitle {
            font-size: 15px;
            opacity: 0.85;
        }
        .content {
            padding: 32px 32px 24px 32px;
            text-align: center;
        }
        .code-box {
            display: inline-block;
            background: #f1f5ff;
            color: #4f46e5;
            font-size: 2.2em;
            font-weight: bold;
            letter-spacing: 0.25em;
            border-radius: 8px;
            padding: 18px 36px;
            margin: 28px 0 18px 0;
            box-shadow: 0 2px 8px #e0e7ff;
        }
        .footer {
            background: #f1f1f1;
            color: #888;
            font-size: 13px;
            text-align: center;
            padding: 18px 0;
        }
        @media (max-width: 600px) {
            .email-container { max-width: 100%; border-radius: 0; }
            .content { padding: 20px 10px 16px 10px; }
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="header">
            <div class="logo">Nexus Engineering</div>
            <div class="subtitle">Profile Update Verification</div>
        </div>
        <div class="content">
            <p>Hello,</p>
            <p>We received a request to update your profile. Please use the following verification code to confirm your changes:</p>
            <div class="code-box">{{ $code }}</div>
            <p style="margin-top: 18px;">This code will expire in 15 minutes.<br>If you did not request this, you can safely ignore this email.</p>
        </div>
        <div class="footer">
            &copy; {{ date('Y') }} Nexus Engineering. All rights reserved.
        </div>
    </div>
</body>
</html> 