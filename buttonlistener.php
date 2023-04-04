<?php

class ButtonEventListener
{
    const DATABASE = '11.22.33.444/XE';
    const USERNAME = 'xyz';
    const PASSWORD = '123';

    /*
     * Open Oracle DB connection
     * */
    private function connect()
    {
        $conn = oci_connect(self::USERNAME, self::PASSWORD, self::DATABASE, 'UTF8');
        if (!$conn) {
            $e = oci_error();
            $this->writeLog("DATABASE CONNECTION FAILED: " . $e['message']);
        }

        return $conn;
    }

    /*
     * Close Oracle DB connection
     * */
    private function closeConn($conn)
    {
        // Close the Oracle connection
        oci_close($conn);
    }

    /*
     * Prepares the queries and update
     * */
    private function updateRows($data)
    {
        $conn = $this->connect();
        $query = "BEGIN ";
        foreach ($data as $item) {
            $id = $item['id'];
            $status = $item['status'];
            $statusInfo = $item['statusInfo'];
            $query .= "UPDATE calendari_metges SET WHATSAPP='" . $status . "', WHATSAPP_INFO='" . $statusInfo . "' WHERE ID=" . $id . ";";
        }
        $query .= " END;";
        $stmt = oci_parse($conn, $query);

        $result = oci_execute($stmt, OCI_DEFAULT);
        if (!$result) {
            $this->writeLog("DATABASE UPDATE FAILED " . oci_error());
        }

        oci_commit($conn);
        oci_free_statement($stmt);
        $this->closeConn($conn);

        return true;
    }

    /*
     * Logger
     * */
    private function writeLog($message)
    {
        $file = __DIR__ . "/logs/buttonListener.log";
        chmod($file, 0777);
        $fh = fopen($file, "a");
        fwrite($fh, $date = date("Y/m/d H:s") . ' -> ' . $message . "\n");
        fclose($fh);
    }

    public function updateClient($client)
    {
        try {
            $patientId = $this->base64url_decode($client);
            $status = 'CO';
            $status_info = 'Enviat correctament';
            $rowsToBeUpdates[] = [
                'id' => $patientId,
                'status' => $status,
                'statusInfo' => $status_info
            ];
            $this->updateRows($rowsToBeUpdates);
//            $this->writeLog('Confirmation for : '. $client);

            return true;
        } catch (Exception $e) {
            $this->writeLog('ERROR: ' . $e->getMessage());

            return false;
        }
    }

    public function base64url_encode($data)
    {

        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    public function base64url_decode($data)
    {

        return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT));
    }
}

$eventListener = new ButtonEventListener();
$patient = $_GET['client'];
$template = '
    <html>
        <body style="display: flex; flex-direction: column; justify-content: center; align-items: center; height: 100%;">
            <img src="https://www.example.com/whatsapp/assets/logo.png" style="width: 60%;">
            <h1 style="font-size: 50px;">{{MESSAGE}}</h1>
        </body>
    </html>
';
if (isset($patient)) {
    $update = $eventListener->updateClient($patient);
    if ($update) {
        header("HTTP/1.1 200 OK");
        $message = 'Gràcies per la seva confirmació';
        echo str_replace('{{MESSAGE}}', $message, $template);
        die();
    } else {
        $this->writeLog('No client found.');
        header("HTTP/1.0 500 Internal Server Error");
        $message = 'Hi ha hagut un problema amb la seva confirmació. Si us plau, contacti amb nosaltres per email a atencio@cepferres.com';
        echo str_replace('{{MESSAGE}}', $message, $template);
        die();
    }
} else {
    $this->writeLog('No client found.');
    header("HTTP/1.0 404 Not Found");
    $message = 'No s\'han trobat clients.';
    echo str_replace('{{MESSAGE}}', $message, $template);
    die();
}

?>
