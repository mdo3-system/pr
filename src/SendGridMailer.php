<?php
/**
 * J-ALG (上善如水 アライアンス・リードジェネレーター)
 * SendGrid Web API v3 メール送信サービスクラス
 */

class SendGridMailer {
    private $apiKey;
    private $fromEmail = "info@jozen-info.com";
    private $fromName  = "WEB構造計算 上善如水 - 壁量・N値・基礎計算ツール";

    public function __construct(?string $apiKey = null) {
        $this->apiKey = $apiKey;
    }

    /**
     * テンプレート文字列内の変数を置換する
     */
    public static function parseTemplate(string $text, array $variables): string {
        foreach ($variables as $key => $val) {
            $text = str_replace('{{' . $key . '}}', $val, $text);
        }
        return $text;
    }

    /**
     * SendGrid Web API v3 用のペイロード配列を構築する
     */
    public function buildPayload(
        string $toEmail,
        string $toName,
        string $subject,
        string $bodyText,
        ?string $bodyHtml = null,
        array $customArgs = []
    ): array {
        $payload = [
            'personalizations' => [
                [
                    'to' => [
                        ['email' => $toEmail, 'name' => $toName]
                    ],
                    'custom_args' => array_map('strval', $customArgs)
                ]
            ],
            'from' => [
                'email' => $this->fromEmail,
                'name'  => $this->fromName
            ],
            'subject' => $subject,
            'content' => [
                [
                    'type'  => 'text/plain',
                    'value' => $bodyText
                ]
            ],
            'tracking_settings' => [
                'click_tracking' => ['enable' => true, 'enable_text' => false],
                'open_tracking'  => ['enable' => true]
            ]
        ];

        if ($bodyHtml) {
            $payload['content'][] = [
                'type'  => 'text/html',
                'value' => $bodyHtml
            ];
        }

        return $payload;
    }

    /**
     * メールを送信する (SendGrid API またはシミュレーション)
     */
    public function send(array $payload): array {
        if (empty($this->apiKey)) {
            // APIキーが未設定の場合は安全な開発・テストシミュレーション
            return [
                'success' => true,
                'status_code' => 202,
                'message_id' => 'simulated_' . uniqid(),
                'mode' => 'simulation'
            ];
        }

        $ch = curl_init('https://api.sendgrid.com/v3/mail/send');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $this->apiKey,
                'Content-Type: application/json'
            ],
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HEADER         => true
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $headers = substr($response, 0, $headerSize);
        curl_close($ch);

        $messageId = null;
        if (preg_match('/X-Message-Id:\s*(.+)/i', $headers, $matches)) {
            $messageId = trim($matches[1]);
        }

        if ($httpCode >= 200 && $httpCode < 300) {
            return [
                'success' => true,
                'status_code' => $httpCode,
                'message_id' => $messageId,
                'mode' => 'live'
            ];
        } else {
            return [
                'success' => false,
                'status_code' => $httpCode,
                'error' => $response,
                'mode' => 'live'
            ];
        }
    }
}
