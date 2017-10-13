<?php

namespace IntegrationTests\REST\Warehouse;

class JobViewerTest extends \PHPUnit_Framework_TestCase
{
    const ENDPOINT = 'rest/v0.1/warehouse/';

    public function setUp()
    {
        $xdmodConfig = array( "decodetextasjson" => true );
        $this->xdmodhelper = new \TestHarness\XdmodTestHelper($xdmodConfig);
    }

    /**
     * Note that this test intentionally hardcodes the available dimensions so
     * that we can confirm that the dimensions are all present and correct for
     * fresh installs and for upgrades. Needless to say, the expected results
     * must be updated when the SUPReMM schema changes.
     */
    public function testDimensions()
    {
        $this->xdmodhelper->authenticate("cd");
        $queryparams = array(
            'realm' => 'SUPREMM'
        );
        $response = $this->xdmodhelper->get(self::ENDPOINT . 'dimensions', $queryparams);

        $this->assertEquals(200, $response[1]['http_code']);

        $resdata = $response[0];

        $this->assertArrayHasKey('success', $resdata);
        $this->assertEquals(true, $resdata['success']);

        $dimids = array();
        foreach ($resdata['results'] as $dimension) {
            $dimids[] = $dimension['id'];
        }

        $expectedDimensions = <<<EOF
[
    "application",
    "catastrophe_bucket_id",
    "cpi",
    "cpucv",
    "cpuuser",
    "datasource",
    "nsfdirectorate",
    "parentscience",
    "exit_status",
    "netdrv_gpfs_rx_bucket_id",
    "grant_type",
    "granted_pe",
    "ibrxbyterate_bucket_id",
    "netdrv_isilon_rx_bucket_id",
    "jobsize",
    "jobwalltime",
    "nodecount",
    "netdrv_panasas_rx_bucket_id",
    "max_mem",
    "pi",
    "fieldofscience",
    "pi_institution",
    "queue",
    "resource",
    "provider",
    "shared",
    "username",
    "person",
    "institution"
]
EOF;
        $this->assertEquals(json_decode($expectedDimensions, true), $dimids);

        $this->xdmodhelper->logout();
    }


    public function testResourceEndPoint()
    {
        $this->xdmodhelper->authenticate("cd");

        $queryparams = array(
            'realm' => 'SUPREMM'
        );

        $response = $this->xdmodhelper->get(self::ENDPOINT . 'dimensions/resource', $queryparams);

        $this->assertEquals(200, $response[1]['http_code']);

        $resdata = $response[0];

        $this->assertArrayHasKey('success', $resdata);
        $this->assertEquals(true, $resdata['success']);

        foreach($resdata['results'] as $resource)
        {
            $this->assertArrayHasKey('id', $resource);
            $this->assertArrayHasKey('name', $resource);
            $this->assertArrayHasKey('short_name', $resource);
            $this->assertArrayHasKey('long_name', $resource);
        }

        $this->xdmodhelper->logout();
    }

    public function testResourceNoAuth()
    {
        $queryparams = array(
            'realm' => 'SUPREMM'
        );
        $response = $this->xdmodhelper->get(self::ENDPOINT . 'dimensions/resource', $queryparams);

        $this->assertEquals(401, $response[1]['http_code']);
    }

    private function validateSingleJobSearch($searchparams, $doAuth = true)
    {
        if ($doAuth) {
            $this->xdmodhelper->authenticate("cd");
        }
        $result = $this->xdmodhelper->get(self::ENDPOINT . 'search/jobs', $searchparams);

        $this->assertArrayHasKey('success', $result[0]);
        $this->assertEquals($result[0]['success'], true);
        $this->assertArrayHasKey('results', $result[0]);
        $this->assertCount(1, $result[0]['results']);

        $jobdata = $result[0]['results'][0];

        $this->assertArrayHasKey('dtype', $jobdata);
        $this->assertArrayHasKey($jobdata['dtype'], $jobdata);

        if ($doAuth) {
            $this->xdmodhelper->logout();
        }

        return $jobdata;
    }

    public function testBasicJobSearch() {
        $queryparams = array(
            'realm' => 'SUPREMM',
            'params' => json_encode(
                array(
                    'resource_id' => 5,
                    'local_job_id' => 6117153
                )
            )
        );
        $this->validateSingleJobSearch($queryparams);
    }

    public function testBasicJobSearchNoAuth() {
        $searchparams = array(
            'realm' => 'SUPREMM',
            'params' => json_encode(
                array(
                    'resource_id' => 5,
                    'local_job_id' => 6117153
                )
            )
        );

        foreach (array('usr', 'pi') as $unpriv) {
            $this->xdmodhelper->authenticate($unpriv);
            $response = $this->xdmodhelper->get(self::ENDPOINT . 'search/jobs', $searchparams);
            $this->assertEquals(403, $response[1]['http_code']);
            $this->assertArrayHasKey('success', $response[0]);
            $this->assertEquals(false, $response[0]['success']);
            $this->xdmodhelper->logout();
        }
    }

    public function testInvalidJobSearch() {

        $this->xdmodhelper->authenticate("cd");
        $result = $this->xdmodhelper->get(self::ENDPOINT . 'search/jobs', array() );

        $this->assertArrayHasKey('success', $result[0]);
        $this->assertEquals($result[0]['success'], false);
        $this->assertEquals($result[1]['http_code'], 400);

        $this->xdmodhelper->logout();
    }

    public function testInvalidJobSearchJson() {

        $searchparams = array(
            "realm" => "SUPREMM",
            "params" => "this is not json data"
        );

        $this->xdmodhelper->authenticate("cd");
        $result = $this->xdmodhelper->get(self::ENDPOINT . 'search/jobs', $searchparams);

        $this->assertArrayHasKey('success', $result[0]);
        $this->assertEquals($result[0]['success'], false);
        $this->assertEquals($result[1]['http_code'], 400);

        $this->xdmodhelper->logout();
    }

    public function testInvalidJobSearchMissingParams() {
 
        $searchparams = array(
            "realm" => "SUPREMM",
            "params" => json_encode(array("resource_id" => "2801"))
        );

        $this->xdmodhelper->authenticate("cd");
        $result = $this->xdmodhelper->get(self::ENDPOINT . 'search/jobs', $searchparams);

        $this->assertArrayHasKey('success', $result[0]);
        $this->assertEquals($result[0]['success'], false);
        $this->assertEquals($result[1]['http_code'], 400);

        $this->xdmodhelper->logout();
    }

    public function testAdvancedSearchInvalid() {
        $searchparams = array(
            "start_date" => "2015-01-01",
            "end_date" => "2015-01-01",
            "realm" => "SUPREMM",
            "params" => json_encode(
                array( "non existent dimension 1" => array(0),
                "another invalid dimension" => array(1) )
            ),
            "limit" => 10,
            "start" => 0
        );

        $this->xdmodhelper->authenticate("cd");
        $result = $this->xdmodhelper->get(self::ENDPOINT . 'search/jobs', $searchparams);
        $this->assertEquals($result[0]['success'], false);
        $this->assertEquals($result[1]['http_code'], 400);

        $this->xdmodhelper->logout();
    }

    public function testJobMetadata() {
        $queryparams = array(
            'realm' => 'SUPREMM',
            'params' => json_encode(
                array(
                    'resource_id' => 5,
                    'local_job_id' => 6112282
                )
            )
        );
        $this->xdmodhelper->authenticate('cd');
        $jobparams = $this->validateSingleJobSearch($queryparams, false);
        $searchparams = array(
            'realm' => 'SUPREMM',
            'recordid' => '-1', // this parameter is not acutally used for anything but needs to be present :-(
            $jobparams['dtype'] => $jobparams[$jobparams['dtype']]
        );

        $result = $this->xdmodhelper->get(self::ENDPOINT . 'search/history', $searchparams);

        $types = array();

        foreach($result[0]['results'] as $datum) {
            $this->assertArrayHasKey('dtype', $datum);
            $this->assertArrayHasKey($datum['dtype'], $datum);
            $this->assertArrayHasKey('text', $datum);
            $types[] = $datum['text'];
        }
        
        $expectedTypes = array(
            'Accounting data',
            'Executable information',
            'Summary metrics',
            'Detailed metrics',
            'Job analytics',
            'Timeseries'
        );

        $this->assertEquals($expectedTypes, $types);
    }

    /**
     * @dataProvider jobTimeseriesProvider
     */
    public function testJobTimeseries($xdmodhelper, $params, $expectedContentType, $expectedFinfo) {
        $response = $xdmodhelper->get(self::ENDPOINT . 'search/jobs/timeseries', $params);

        $this->assertEquals(200, $response[1]['http_code']);
        $this->assertEquals($expectedContentType, $response[1]['content_type']);

        if ($expectedFinfo !== null) {
            // Check the mime type of the file is correct.
            $finfo = finfo_open(FILEINFO_MIME);
            $this->assertEquals($expectedFinfo, finfo_buffer($finfo, $response[0]));
        }
    }

    public function jobTimeseriesProvider() {
        $xdmodhelper = new \TestHarness\XdmodTestHelper();
        $xdmodhelper->authenticate('cd');

        $queryparams = array(
            'realm' => 'SUPREMM',
            'params' => json_encode(
                array(
                    'resource_id' => 5,
                    'local_job_id' => 6112282
                )
            )
        );
        $result = $xdmodhelper->get(self::ENDPOINT . 'search/jobs', $queryparams);
        $jobparams = $result[0]['results'][0];

        $searchparams = array(
            'realm' => 'SUPREMM',
            $jobparams['dtype'] => $jobparams[$jobparams['dtype']],
            'infoid' => 6,
            'tsid' =>  'cpuuser'
        );

        $ret = array();
        $ret[] = array($xdmodhelper, $searchparams, 'application/json', null);
        $searchparams['format'] = 'pdf';
        $ret[] = array($xdmodhelper, $searchparams, 'application/pdf', 'application/pdf; charset=binary');
        $searchparams['format'] = 'csv';
        $ret[] = array($xdmodhelper, $searchparams, 'text/csv', 'text/plain; charset=us-ascii');
        $searchparams['format'] = 'png';
        $ret[] = array($xdmodhelper, $searchparams, 'image/png', 'image/png; charset=binary');
        $searchparams['format'] = 'svg';
        $ret[] = array($xdmodhelper, $searchparams, 'image/svg+xml', 'text/plain; charset=us-ascii');

        return $ret;
    }
}