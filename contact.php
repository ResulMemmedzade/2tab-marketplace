<?php
session_start();
$user_id = $_SESSION['user_id'] ?? null;
?>

<!DOCTYPE html>
<html lang="az">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Əlaqə - 2tab</title>
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f8fafc;
            color: #1e293b;
        }

        .page {
            max-width: 520px;
            margin: 0 auto;
            padding: 20px 16px 40px;
        }

        .top-row {
            display: flex;
            align-items: center;
            margin-bottom: 18px;
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 42px;
            height: 42px;
            border-radius: 12px;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            text-decoration: none;
            color: #0f172a;
            font-size: 22px;
            font-weight: bold;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06);
        }

        .back-btn:hover {
            background: #f1f5f9;
        }

        .card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 20px;
            padding: 22px 18px;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.06);
        }

        h1 {
            margin: 0 0 8px;
            font-size: 28px;
            color: #0f172a;
            text-align: center;
        }

        .subtitle {
            margin: 0 0 22px;
            text-align: center;
            color: #64748b;
            line-height: 1.5;
            font-size: 14px;
        }

        .contact-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .contact-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            width: 100%;
            padding: 16px 14px;
            border-radius: 16px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            text-decoration: none;
            color: #0f172a;
            transition: 0.2s ease;
        }

        .contact-item:hover {
            background: #eff6ff;
            border-color: #bfdbfe;
        }

        .contact-left {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 0;
        }

        .icon {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            background: #dbeafe;
            color: #2563eb;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            flex-shrink: 0;
        }

        .contact-text {
            min-width: 0;
        }

        .contact-title {
            font-size: 15px;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .contact-value {
            font-size: 14px;
            color: #475569;
            word-break: break-word;
        }

        .arrow {
            color: #94a3b8;
            font-size: 18px;
            flex-shrink: 0;
        }

        

        .note {
            margin-top: 14px;
            text-align: center;
            color: #64748b;
            font-size: 13px;
            line-height: 1.5;
        }

        @media (max-width: 480px) {
            .page {
                padding: 14px 12px 30px;
            }

            .card {
                padding: 18px 14px;
                border-radius: 18px;
            }

            h1 {
                font-size: 24px;
            }

            .contact-item {
                padding: 14px 12px;
            }

            .icon {
                width: 38px;
                height: 38px;
                font-size: 18px;
            }
        }
    </style>
</head>
<body>

<div class="page">
    <div class="top-row">
        <a href="javascript:history.back()" class="back-btn" aria-label="Geri">←</a>
    </div>

    <div class="card">
        <h1>Bizimlə əlaqə</h1>
        <p class="subtitle">
            Sualların, təkliflərin və ya hər hansı problemin varsa, aşağıdakı vasitələrlə bizimlə əlaqə saxlaya bilərsən.
        </p>

        <div class="contact-list">

            <a class="contact-item" href="https://instagram.com/2tab.store" target="_blank" rel="noopener noreferrer">
                <div class="contact-left">
                    <div class="icon">📸</div>
                    <div class="contact-text">
                        <div class="contact-title">Instagram</div>
                        <div class="contact-value">2tab.store</div>
                    </div>
                </div>
                <div class="arrow">›</div>
            </a>

            <a class="contact-item" href="mailto:2tab.books@gmail.com">
                <div class="contact-left">
                    <div class="icon">📧</div>
                    <div class="contact-text">
                        <div class="contact-title">Gmail</div>
                        <div class="contact-value">2tab.books@gmail.com</div>
                    </div>
                </div>
                <div class="arrow">›</div>
            </a>

            <a class="contact-item" href="tel:+994773007540">
                <div class="contact-left">
                    <div class="icon">📞</div>
                    <div class="contact-text">
                        <div class="contact-title">Telefon</div>
                        <div class="contact-value">077 300 75 40</div>
                    </div>
                </div>
                <div class="arrow">›</div>
            </a>

        </div>

        

        <div class="note">
            Cavablar mümkün qədər qısa müddətdə veriləcək.
        </div>
    </div>
</div>

</body>
</html>