<?php

namespace tests;

use \AMQPMessage\CameraEvent;
use \AMQPMessage\CameraEventImageAws3;

/**
 * Class CameraEventImageFileStoreTest
 * @package tests
 */
class CameraEventImageFileStoreTest extends \PHPUnit_Framework_TestCase {
    /**
     * @var $service object mock \QFreeLPR\Service
     */
    protected $service;

    /**
     * @var $methodStoreImage null | \ReflectionMethod
     */
    protected $methodStoreImage;

    /**
     * @var $methodCreateParkingEvent null | \ReflectionMethod
     */
    protected $methodCreateParkingEvent;

    /**
     * @var $parkingEvent null | \ParkingEvent
     */
    protected $parkingEvent;

    /**
     * @var $file null | \FileEps | \FileAws3
     */
    protected $file;

    protected function setUp() {
        $this->service = $this->getMockBuilder('\QFreeLPR\Service')
            ->setMethods(array('__construct'))
            ->setConstructorArgs([])
            ->disableOriginalConstructor()
            ->getMock();

        $reflection = new \ReflectionClass(get_class($this->service));
        $this->methodStoreImage = $reflection->getMethod('storeImage');
        $this->methodStoreImage->setAccessible(true);
        $this->methodCreateParkingEvent = $reflection->getMethod('createParkingEvent');
        $this->methodCreateParkingEvent->setAccessible(true);

        parent::setUp();
    }

    public function dataProviderForTestStoreImage() {
        return [
            [
                CameraEvent::create(
                    2070,
                    '12/12/2016 14:46:50.000000',
                    '2016-12-16T17:03:21.389+02:00',
                    base64_encode('thequickbrownfoxjumpsoverthelazydog'),
                    950,
                    'NOR',
                    'AA11111',
                    null,
                    null,
                    'HERE',
                    'ENTRY'
                ), 'test' => ['instance' => 'FileEps']
            ], [
                CameraEvent::create(
                    2070,
                    '12/12/2016 14:46:50.000000',
                    '2016-12-16T17:03:21.389+02:00',
                    null,
                    950,
                    'NOR',
                    'AA11111',
                    null,
                    null,
                    'HERE',
                    'EXIT'
                ), 'test' => ['instance' => 'FileEps']
            ], [
                CameraEvent::create(
                    2070,
                    '12/12/2016 14:46:50.000000',
                    '2016-12-16T17:03:21.389+02:00',
                    new CameraEventImageAws3([
                        'object_id' => '08a34a1f081e4bd69c3c1947c77299f9.jpg',
                        'bucket_id' => 'helmes-test',
                        'service_name' => 'INVALID-IDENTIFIER'
                    ]),
                    950,
                    'NOR',
                    'AA11111',
                    null,
                    null,
                    'HERE',
                    'EXIT'
                ), 'test' => ['instance' => 'FileEps']
            ], [
                CameraEvent::create(
                    2070,
                    '12/12/2016 14:46:50.000000',
                    '2016-12-16T17:03:21.389+02:00',
                    new CameraEventImageAws3([
                        'object_id' => '08a34a1f081e4bd69c3c1947c77299f9.jpg',
                        'bucket_id' => 'helmes-test',
                        'service_name' => 'AWS_S3'
                    ]),
                    950,
                    'NOR',
                    'AA11111',
                    null,
                    null,
                    'HERE',
                    'EXIT'
                ), 'test' => ['instance' => 'FileAws3']
            ], [
                CameraEvent::create(
                    null,
                    null,
                    null,
                    null,
                    null,
                    null,
                    null
                ), 'test' => ['instance' => 'FileEps']]
        ];
    }

    /**
     * @param CameraEvent $cameraEvent
     * @param $test array extra data to test against
     * @dataProvider dataProviderForTestStoreImage
     */
    public function testStoreImage(CameraEvent $cameraEvent, $test) {
        $parkingEvent = $this->methodCreateParkingEvent->invokeArgs($this->service, [$cameraEvent, $cameraEvent->direction2]);
        $this->file = $file = $this->methodStoreImage->invokeArgs($this->service, [$cameraEvent, $parkingEvent]);
        $this->assertInstanceOf($test['instance'], $this->file);

        switch ($test['instance'] === 'FileAws3') {
            case 'FileAws3':
                /** @var \FileAws3 $file */
                $this->assertEquals($file->name, $cameraEvent->image->object_id);
                $testAws3Resource = $cameraEvent->image->bucket_id . '/' . $cameraEvent->image->object_id;
                $this->assertEquals($file->file_name, $testAws3Resource);
                break;
            default:
                /** @var \FileEps $file */
                $path = $file->getFilePath();
                $this->assertFileExists($path);
                $this->assertEquals(strlen(@base64_decode($cameraEvent->image)), filesize($path));
        }
    }

    /**
     * @expectedException \Exception
     * @expectedExceptionMessage AWS S3 resource info (object_id) missing!
     */
    public function testFileAws3InvalidObjectIdThrowsException() {
        $cameraEvent = CameraEvent::create(
            null, null, null,
            new CameraEventImageAws3([
                'object_id' => null,
                'bucket_id' => 'some-bucket',
                'service_name' => 'AWS_S3'
            ]), null, null, null
        );
        $file = \File::getInstanceFromCameraEvent($cameraEvent);
        $file->processCameraEventImage($cameraEvent->image);
    }

    /**
     * @expectedException \Exception
     * @expectedExceptionMessage AWS S3 bucket info (bucket_id) missing!
     */
    public function testFileAws3InvalidBucketIdThrowsException() {
        $cameraEvent = CameraEvent::create(
            null, null, null,
            new CameraEventImageAws3([
                'object_id' => 'someobject',
                'bucket_id' => null,
                'service_name' => 'AWS_S3'
            ]), null, null, null
        );
        $file = \File::getInstanceFromCameraEvent($cameraEvent);
        $file->processCameraEventImage($cameraEvent->image);
    }

    public function tearDown() {
        if (is_object($this->file)) $this->file->destroyInstance();
        if (is_object($this->parkingEvent)) $this->parkingEvent->destroyInstance();
        parent::tearDown();
    }
}