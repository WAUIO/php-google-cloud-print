<?php
/*
PHP implementation of Google Cloud Print
Author, Yasir Siddiqui

Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions are met:

* Redistributions of source code must retain the above copyright notice, this
  list of conditions and the following disclaimer.

* Redistributions in binary form must reproduce the above copyright notice,
  this list of conditions and the following disclaimer in the documentation
  and/or other materials provided with the distribution.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE
FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL
DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR
SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY,
OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
*/

namespace GCPrint;


class GoogleCloudPrint
{

    const PRINTERS_SEARCH_URL = "https://www.google.com/cloudprint/search";
    const PRINT_URL = "https://www.google.com/cloudprint/submit";
    const JOBS_URL = "https://www.google.com/cloudprint/jobs";

    private $authtoken;
    private $httpRequest;
    private $refreshtoken;

    /**
     * Function __construct
     * Set private members varials to blank
     */
    public function __construct ()
    {

        $this->authtoken = "";
        $this->httpRequest = new HttpRequest();
    }

    /**
     * Function setAuthToken
     *
     * Set auth tokem
     * @param string $token token to set
     */
    public function setAuthToken ($token)
    {
        $this->authtoken = $token;
    }

    /**
     * Function getAuthToken
     *
     * Get auth tokem
     * return auth tokem
     */
    public function getAuthToken ()
    {
        return $this->authtoken;
    }


    /**
     * Function getAccessTokenByRefreshToken
     *
     * Gets access token by making http request
     *
     * @param $url url to post data to
     *
     * @param $post_fields post fileds array
     *
     * return access tokem
     */

    public function getAccessTokenByRefreshToken ($url, $post_fields)
    {
        $responseObj = $this->getAccessToken($url, $post_fields);
        return $responseObj->access_token;
    }


    /**
     * Function getAccessToken
     *
     * Makes Http request call
     *
     * @param $url url to post data to
     *
     * @param $post_fields post fileds array
     *
     * return http response
     */
    public function getAccessToken ($url, $post_fields)
    {
        $this->httpRequest->setHeaders(["Content-Type: application/x-www-form-urlencoded"]);
        $this->httpRequest->setUrl($url);
        $this->httpRequest->setPostData($post_fields);
        $this->httpRequest->send();
        $response = json_decode($this->httpRequest->getResponse());
        return $response;
    }

    /**
     * Used to check the printer status and if it can print now
     *
     * @param $printerId
     * @return array
     */
    protected function checkPrinter ($printerId)
    {
        //try to fetch all printers first
        try {
            $allPrinters = $this->getPrinters();
        } catch (\Exception $e) {
            return ["got" => false, "message" => "Could not get any printer sorry : ".$e->getMessage()];
        }

        //only filter the printer with given id
        $myPrinter = array_filter($allPrinters, function ($val) use ($printerId) {
            if ($val['id'] == $printerId)
                return true;
            else
                return false;
        });

        //could not get the printer with given id
        if (empty($myPrinter))
            return ["got" => false, "message" => "Could not get printer with id ".$printerId];

        //just for convenience
        $myPrinter = $myPrinter [0];

        //check if printer is not online
        if ($myPrinter['connectionStatus'] != "ONLINE")
            return ["got" => false, "message" => "Got the printer ".$myPrinter['name']. " but its status is currently ".$myPrinter['connectionStatus']. ". Please make sure it is online before you try to print again."];

        //return true if all is OK
        return ["got" => true, "message" => "Got printer ".$myPrinter['name']];
    }

    /**
     * Function getPrinters
     *
     * Get all the printers added by user on Google Cloud Print.
     * Follow this link https://support.google.com/cloudprint/answer/1686197 in order to know how to add printers
     * to Google Cloud Print service.
     */
    public function getPrinters ()
    {

        // Check if we have auth token
        if (empty($this->authtoken)) {
            // We don't have auth token so throw exception
            throw new \Exception("Please first login to Google");
        }

        // Prepare auth headers with auth token
        $authheaders = array(
            "Authorization: Bearer " . $this->authtoken
        );

        $this->httpRequest->setUrl(self::PRINTERS_SEARCH_URL);
        $this->httpRequest->setHeaders($authheaders);
        $this->httpRequest->send();
        $responsedata = $this->httpRequest->getResponse();
        // Make Http call to get printers added by user to Google Cloud Print
        $printers = json_decode($responsedata);
        // Check if we have printers?
        if (is_null($printers)) {
            // We dont have printers so return balnk array
            return array();
        } else {
            // We have printers so returns printers as array
            return $this->parsePrinters($printers);
        }

    }

    /**
     * Function sendPrintToPrinter
     *
     * Sends document to the printer
     *
     * @param Printer id $printerid    // Printer id returned by Google Cloud Print service
     *
     * @param Job Title $printjobtitle // Title of the print Job e.g. Fincial reports 2012
     *
     * @param File Path $filepath      // Path to the file to be send to Google Cloud Print
     *
     * @param Content Type $contenttype // File content type e.g. application/pdf, image/png for pdf and images
     */
    public function sendPrintToPrinter ($printerid, $printjobtitle, $filepath, $contenttype)
    {

        // Check if we have auth token
        if (empty($this->authtoken)) {
            // We don't have auth token so throw exception
            throw new \Exception("Please first login to Google by calling loginToGoogle function");
        }
        // Check if prtinter id is passed
        if (empty($printerid)) {
            // Printer id is not there so throw exception
            throw new \Exception("Please provide printer ID");
        }

        //check if the printer is ready to print
        list ($gotPrinter, $fetchPrinterMessage) = $this->checkPrinter($printerid);

        if (!$gotPrinter) {
            return array('status' => false, 'errorcode' => '', 'errormessage' => $fetchPrinterMessage, 'id' => 0);
        }


        // Open the file which needs to be print
        $handle = fopen($filepath, "rb");
        if (!$handle) {
            // Can't locate file so throw exception
            throw new \Exception("Could not read the file. Please check file path.");
        }
        // Read file content
        $contents = fread($handle, filesize($filepath));
        fclose($handle);

        // Prepare post fields for sending print
        $post_fields = array(

            'printerid'               => $printerid,
            'title'                   => $printjobtitle,
            'contentTransferEncoding' => 'base64',
            'content'                 => base64_encode($contents), // encode file content as base64
            'contentType'             => $contenttype
        );
        // Prepare authorization headers
        $authheaders = array(
            "Authorization: Bearer " . $this->authtoken
        );

        // Make http call for sending print Job
        $this->httpRequest->setUrl(self::PRINT_URL);
        $this->httpRequest->setPostData($post_fields);
        $this->httpRequest->setHeaders($authheaders);
        $this->httpRequest->send();
        $response = json_decode($this->httpRequest->getResponse());

        // Has document been successfully sent?
        if ($response->success == "1") {

            return array('status' => true, 'errorcode' => '', 'errormessage' => "", 'id' => $response->job->id);
        } else {

            return array('status' => false, 'errorcode' => $response->errorCode, 'errormessage' => $response->message);
        }
    }

    public function jobStatus ($jobid)
    {
        // Prepare auth headers with auth token
        $authheaders = array(
            "Authorization: Bearer " . $this->authtoken
        );

        // Make http call for sending print Job
        $this->httpRequest->setUrl(self::JOBS_URL);
        $this->httpRequest->setHeaders($authheaders);
        $this->httpRequest->send();
        $responsedata = json_decode($this->httpRequest->getResponse());

        foreach ($responsedata->jobs as $job) {
            if ($job->id == $jobid) {
                return $job->status;
            }
        }

        return 'UNKNOWN';
    }


    /**
     * Function parsePrinters
     *
     * Parse json response and return printers array
     *
     * @param $jsonobj // Json response object
     *
     */
    private function parsePrinters ($jsonobj)
    {

        $printers = array();
        if (isset($jsonobj->printers)) {
            foreach ($jsonobj->printers as $gcpprinter) {
                $printers[] = array(
                    'id'               => $gcpprinter->id,
                    'name'             => $gcpprinter->name,
                    'displayName'      => $gcpprinter->displayName,
                    'ownerName'        => @$gcpprinter->ownerName,
                    'connectionStatus' => $gcpprinter->connectionStatus,
                );
            }
        }
        return $printers;
    }
}
