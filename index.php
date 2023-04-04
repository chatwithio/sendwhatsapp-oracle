<?php
require_once "WhatsApp360.php";

error_reporting(E_ERROR);
ini_set("display_errors", 1);
ini_set("memory_limit", "256M");
ini_set('max_execution_time', 0);
set_time_limit(0);

// cron: wget --no-check-certificate https://www.cepferres.com/whatsapp/index2.php -O /dev/null
class CepferresWhatsapp
{
    private $whatsapp360;
    const DATABASE = '11.22.33.44/XE';
    const USERNAME = 'xxxx';
    const PASSWORD = '12345';

    public function __construct()
    {
        $this->whatsapp360 = new WhatsApp360();
    }

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
     * Get patient rows from tomorrow
     * */
    private function getRows()
    {
        $conn = $this->connect();

        $data = null;
        $sql = "
            SELECT calendari_metges.id,USUARIS.NOM || ' ' || USUARIS.cogNOMs AS dname, USUARIS.SEXE, pacient, pacients.nom || ' ' || pacients.cognoms as pname, TMM, TMP,
                   tipus_visita.descripcio as TipusV, calendari_metges.data as DataV, calendari_metges.Hora as HoraV, calendari_metges.ID, pacients.SMSM, pacients.SMSP, calendari_metges.WHATSAPP,
                   calendari_metges.WHATSAPP_INFO
            FROM calendari_metges, pacients, usuaris, tipus_visita
            WHERE pacient=pacients.id AND metge=usuaris.id
            AND calendari_metges.tipus=tipus_visita.ID_TIPUS AND calendari_metges.especialitat=tipus_visita.especialitat
            AND TRUNC (DATA) = TRUNC(sysdate) + numtodsinterval(1, 'day')
            AND calendari_metges.WHATSAPP IS NULL
            ORDER BY calendari_metges.data, calendari_metges.Hora ASC
        ";

        //parse query
        $stid = oci_parse($conn, $sql);
        if (!$stid) {
            $e = oci_error($conn);
            $this->writeLog("DATABASE SELECT ERROR LOGGED: " . $e['message']);
        }

        // execute query
        $r = oci_execute($stid);

        // Fetch each row in the array
        while ($row = oci_fetch_array($stid, OCI_RETURN_NULLS + OCI_ASSOC)) {
            $data[] = $row;
        }
        $this->closeConn($conn);

        return $data;
    }

    /*
     * Prepares the batch queries and updates all once
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
     * Main function
     * */
    public function run()
    {
        try {
            $rowsToBeUpdates = [];
            $numberVisits = 0;
            $numberVisitsKO = 0;
            $numberVisitsOK = 0;

            $todaysData = $this->getRows();

            foreach ($todaysData as $row) {
                $doctorName = $row['DNAME'];
                $patientName = $row['PNAME'];
                $patientSex = $row['SEXE'];
                $phoneNoMother = $row['TMM'];
                $phoneNoMotherSet = $row['SMSM'];
                $phoneNoFather = $row['TMP'];
                $phoneNoFatherSet = $row['SMSP'];
                $date = date('d/m/Y', strtotime($row['DATAV']));
                $time = $row['HORAV'];

                // get the Catalan name of week
                $dayOfWeekNo = date('w', strtotime($row['DATAV']));
                $days = [
                    1 => 'Dilluns',
                    2 => 'Dimarts',
                    3 => 'Dimecres',
                    4 => 'Dijous',
                    5 => 'Divendres',
                    6 => 'Dissabte',
                    7 => 'Diumenge',
                ];
                $day = $days[$dayOfWeekNo];

                // this logic checks if mother and father are marked to be contacted
                // mother has always prio to father
                $checkMotherNo = false;
                $phoneNoMotherSet = $phoneNoMotherSet == 'S';
                if ($phoneNoMother && $phoneNoMotherSet) {
                    $phoneNoMother = $this->checkPhoneNumber($phoneNoMother);
                    // remove the + from phone first for check
                    if ($this->whatsapp360->checkContact(substr($phoneNoMother, 1)) === true) {
                        $checkMotherNo = true;
                    }
                }

                $checkFatherNo = false;
                $phoneNoFatherSet = $phoneNoFatherSet == 'S';
                if ($phoneNoFather && $phoneNoFatherSet) {
                    $phoneNoFather = $this->checkPhoneNumber($phoneNoFather);
                    // remove the + from phone first for check
                    if ($this->whatsapp360->checkContact(substr($phoneNoFather, 1)) === true) {
                        $checkFatherNo = true;
                    }
                }

                $patientPhoneNumber = false;
                if ($checkMotherNo && $checkFatherNo) {
                    $patientPhoneNumber = $phoneNoMother;
                } elseif ($checkMotherNo) {
                    $patientPhoneNumber = $phoneNoMother;
                } elseif ($checkFatherNo) {
                    $patientPhoneNumber = $phoneNoFather;
                }

                $placeholders = [$day . ' ' . $date, $time, $patientName, $doctorName];

                // OLD Templates without button
//                $template = ($patientSex == 'H') ? 'recordatori_doctor' : 'recordatori_doctora';
//                $response = $patientPhoneNumber ? $this->whatsapp360->sendWhatsApp($patientPhoneNumber, $placeholders, $template, 'ca', '05946315_8ea1_222222_1111111') : '';

                // NEW Templates with button for confirmation
                $template = ($patientSex == 'H') ? 'recordatorio_doctor_boton' : 'recordatorio_doctora_boton';
                $patientId = $this->base64url_encode($row['ID']);
                $response = $patientPhoneNumber ? $this->whatsapp360->sendWhatsAppWithButton($patientPhoneNumber, $placeholders, $template, 'ca', '05946315_8ea1_222222_1111111', $patientId) : '';

                // prepare status info for DB update
                $status = 'OK';
                $status_info = 'Enviat correctament';
                if (!isset($response->contacts)) {
                    $numberVisitsKO += 1;
                    $status = 'KO';
                    $status_info = 'Mobils incorrectes o inexistents';
                    $this->writeLog('[ID ' . $row['ID'] . '] - NOK: ' . $response);
                } else {
                    $numberVisitsOK += 1;
                    // $this->writeLog('[ID ' . $row['ID'] . '] - OK');
                }
                $numberVisits += 1;

                $rowsToBeUpdates[] = [
                    'id' => $row['ID'],
                    'status' => $status,
                    'statusInfo' => $status_info
                ];
            }

            // update database
            if ($rowsToBeUpdates) {
                $this->updateRows($rowsToBeUpdates);
            }

            // send the notification to admin
            $this->sendNotificationToAdmin($numberVisits, $numberVisitsOK, $numberVisitsKO, "34666777888");
            $this->sendNotificationToAdmin($numberVisits, $numberVisitsOK, $numberVisitsKO, "34666777888");


        } catch (Exception $exception) {
            $this->writeLog("GENERAL ERROR LOGGED:" . $exception->getMessage());
        }
    }

    /*
     * Send the final template with notification to ADMIN (JERONI)
     * */
    private function sendNotificationToAdmin($numberVisits, $numberVisitsOK, $numberVisitsKO, $patientPhoneNumber)
    {
        //$patientPhoneNumber = '34666777888';
        //$date = date("d-m-Y");
        $fecha_manana = date('d-m-Y', strtotime('+1 day'));
        setlocale(LC_TIME, 'ca_ES.UTF-8');
        $dia_manana = strftime('%A', strtotime('+1 day'));
        $datafdema = $dia_manana . " " . $fecha_manana;

        $template = ($numberVisitsKO == 0) ? 'informe_final_ok' : 'informe_final_ko';
        $placeholders = ($numberVisitsKO == 0) ? [$datafdema, (string)$numberVisits, (string)$numberVisitsOK] : [$datafdema, (string)$numberVisits, (string)$numberVisitsOK, (string)$numberVisitsKO];

        $response = $this->whatsapp360->sendWhatsApp($patientPhoneNumber, $placeholders, $template, 'ca', '05946315_8ea1_222222_1111111');
        if (!isset($response->contacts)) {
            $this->writeLog('ADMIN - NOK: ' . $response);
        } else {
//            $this->writeLog('OK');
        }
    }

    /*
     * Logger
     * */
    public function writeLog($message)
    {
        $file = __DIR__ . "/logs/error.log";
        chmod($file, 0777);
        $fh = fopen($file, "a");
        fwrite($fh, $date = date("Y/m/d H:s") . ' -> ' . $message . "\n");
        fclose($fh);
    }

    /*
     * Phone Validator
     * */
    private function checkPhoneNumber($phoneNo)
    {
        $phoneNo = str_replace(' ', '', $phoneNo);
        $phoneNo = str_replace('.', '', $phoneNo);
        if (strlen($phoneNo) < 10) {
            $phoneNo = '+34' . $phoneNo;
        }

        return $phoneNo;
    }

    public function base64url_encode($data) {

        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    public function base64url_decode($data)
    {

        return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT));
    }
}

$job = new CepferresWhatsapp();
$job->run();



