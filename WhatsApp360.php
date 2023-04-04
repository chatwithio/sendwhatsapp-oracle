<?php

/**
 * Class WhatsApp360
 *
 * A basic starter class to send templated text only messages via 360 degree whatsapp api
 */

class WhatsApp360
{
    //The api endpoints. There are more but these are the important ones
    private $endpoint = [
        'contact' => [
            'method' => 'POST',
            'url' => 'https://waba.360dialog.io/v1/contacts'
        ],
        'message' => [
            'method' => 'POST',
            'url' => 'https://waba.360dialog.io/v1/messages'
        ]
        ,
        'template' => [
            'method' => 'GET',
            'url' => 'https://waba.360dialog.io/v1/configs/templates'
        ],
    ];

    //The header information. It contains the auth token too
    private $headers = [
        'Content-Type' => 'application/json',
        'D360-API-KEY' => null
    ];

    //False of the telephone number has KOed by whatsapp,
    //eg. if the user does not have whatsapp or it is a malformed number
    private $payloadOk = true;

    /**
     * WhatsApp360 constructor.
     */
    public function __construct()
    {
        $this->headers['D360-API-KEY'] = '';
    }

    /**
     * @param $endpoint (The endpoint to be used - see private $endpoint)
     * @param array $data
     * @return void (if there is any error an exception should be thrown)
     * @throws Exception
     */

    private function send($endpoint, $data = [])
    {
        try {
            if ($this->payloadOk === true) {
                $request = $this->curlRequest($this->endpoint[$endpoint]['method'], $this->endpoint[$endpoint]['url'], $data);

                if ($request['status'] == 200 || $request['status'] == 201) {
                    return json_decode($request['body']);
                } else {
                    throw new Exception($request['body']);
                }
            } else {
                throw new Exception('Unvalidated payload Exception');
            }
        } catch (Exception $exception) {
            $this->logErrors($exception);
        }
    }


    private function buildMessage($messageTemplate, $placeholders)
    {
        $counter = 1;
        foreach ($placeholders as $placeholder) {
            $messageTemplate = str_replace("{$counter}", $placeholder, $messageTemplate);
        }
        return $messageTemplate;
    }

    /**
     * @param $contact (whatapp number with no + or spacing)
     * @return bool (return true if the contact id OKed)
     * @throws \Exception
     */

    public function checkContact($contact)
    {
        try {
            $payload = [
                "blocking" => "wait",
                "contacts" => ["+" . $contact],
                "force_check" => true
            ];
            $response = $this->send('contact', $payload);
            if (!empty($response->contacts)) {
                return true;
            } else {
                return false;
            }
        } catch (Exception $exception) {
            $this->logErrors($exception);
        }
    }

    /**
     * @param $placeholders (an array of text only placeholders)
     * @return array
     */

    private function buildParams($placeholders)
    {
        $arr = [];
        foreach ($placeholders as $placeholder) {
            $arr[] = [
                "type" => "text",
                "text" => $placeholder
            ];
        }
        return $arr;
    }

    /**
     * @param $to (whatapp number  - no spaces or = )
     * @param $placeholders array (of placeholders)
     * @param $template string (template name)
     * @param $language languale (ie en, must match the language of the approved template)
     * @param $namespace  string (template namespace  - you can get this from the getTemplates api)
     * @throws \Exception
     *
     * NOTE, a template must be approved before it can be used
     */

    public function sendWhatsApp($to, $placeholders, $template, $language, $namespace)
    {
        $this->checkContact($to);
        $payload = [
            "to" => $to,
            "type" => "template",
            "template" => [
                "namespace" => $namespace,
                "language" => [
                    "policy" => "deterministic",
                    "code" => $language
                ],
                "name" => $template,
                "components" => [
                    [
                        "type" => "body",
                        "parameters" => $this->buildParams($placeholders)

                    ]
                ]
            ]
        ];
        return $this->send('message', $payload);
    }

    /**
     * @return array (list of templates  - with their nmespaces and approved status)
     */

    public function getTemplates()
    {
        return $this->send('template');
    }

    private function logErrors(Exception $exception)
    {
        return "ERROR LOGGED:" . $exception->getMessage() . "\n";
    }

    public function curlRequest($method, $url, $data)
    {
        try {
            $curl = curl_init();
            curl_setopt_array($curl, array(
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_POSTFIELDS => json_encode($data),
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'D360-API-KEY: '
                ],
            ));

            $response = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            curl_close($curl);

            return [
                'status' => $httpCode,
                'body' => $response
            ];
        } catch (Exception $exception) {
            print "ERROR LOGGED:" . $exception->getMessage() . "\n";
        }
    }

    public function sendWhatsAppWithButton($to, $placeholders, $template, $language, $namespace, $patient)
    {
        $this->checkContact($to);
        $payload = [
            "to" => $to,
            "type" => "template",
            "template" => [
                "namespace" => $namespace,
                "language" => [
                    "policy" => "deterministic",
                    "code" => $language
                ],
                "name" => $template,
                "components" => [
                    [
                        "type" => "body",
                        "parameters" => $this->buildParams($placeholders)
                    ],
                    [
                        "type" => "button",
                        "sub_type" => "url",
                        "index" => "0",
                        "parameters" => [
                            [
                                "type" => "text",
                                "text" => $patient
                            ]
                        ]
                    ]
                ]
            ]
        ];

        return $this->send('message', $payload);
    }
}



