<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this Module to
 * newer versions in the future.
 *
 * @category   Magento
 * @package    VinaiKopp_LoginLog
 * @copyright  Copyright (c) 2014 Vinai Kopp http://netzarbeiter.com
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Class VinaiKopp_LoginLog_Test_Controller_Adminhtml_VinaiKopp_LoginlogControllerTest
 * 
 * @coversDefaultClass VinaiKopp_LoginLog_Adminhtml_LoginlogController
 */
class VinaiKopp_LoginLog_Test_Controller_Adminhtml_VinaiKopp_LoginlogControllerTest
    extends EcomDev_PHPUnit_Test_Case_Controller
{
    protected $class = 'VinaiKopp_LoginLog_Adminhtml_LoginlogController';

    public function setUp()
    {
        parent::setUp();
        $dir = Mage::getModuleDir('controllers', 'VinaiKopp_LoginLog');
        $file = "$dir/Adminhtml/LoginlogController.php";
        $result = stream_resolve_include_path($file);
        if (false !== $result) {
            require_once $file;
        }
        
        $helper = new VinaiKopp_LoginLog_Test_TestHelper();
        $helper->prepareAdminRequest();
        
        $store = $this->app()->getStore('admin');
        $store->setConfig('dev/template/allow_symlink', 1);
    }
    
    public function tearDown()
    {
        parent::tearDown();
        Mage::unregister('_singleton/index/indexer');
    }

    /**
     * @return VinaiKopp_LoginLog_Adminhtml_LoginLogController
     */
    public function getInstance()
    {

        $request = $this->app()->getRequest();
        $response = $this->app()->getResponse();
        return new $this->class($request, $response);
    }

    /**
     * @test
     */
    public function itShouldCheckTheAcl()
    {
        $ctrl = $this->getInstance();

        $mockSession = $this->getModelMock('admin/session');
        $mockSession->expects($this->once())
            ->method('isAllowed')
            ->with('customer/login_log')
            ->will($this->returnValue(true));
        Mage::unregister('_singleton/admin/session');
        Mage::register('_singleton/admin/session', $mockSession);

        $method = new ReflectionMethod($this->class, '_isAllowed');
        $method->setAccessible(true);
        $result = $method->invoke($ctrl, '_isAllowed');
        $this->assertTrue($result);
    }

    /**
     * @covers ::indexAction
     */
    public function testIndexAction()
    {
        $this->dispatch('adminhtml/loginlog/index');
        $this->assertLayoutLoaded();
        $this->assertLayoutHandleLoaded('adminhtml_loginlog_index');
        $this->assertLayoutBlockTypeOf('vinaikopp.loginlog.list', 'vinaikopp_loginlog/adminhtml_loginLog_list');
        $this->assertLayoutBlockCreated('vinaikopp.loginlog.list');
        $this->assertLayoutBlockInstanceOf('vinaikopp.loginlog.list', 'VinaiKopp_LoginLog_Block_Adminhtml_LoginLog_List');
        
        // Block not 
        $child = $this->app()->getLayout()->getBlock('adminhtml_loginLog_list.grid');
        $this->assertInstanceOf('Mage_Adminhtml_Block_Widget_Grid', $child);
    }


    /**
     * @covers ::gridAction
     */
    public function testGridAction()
    {
        $this->dispatch('adminhtml/loginlog/grid');
        $this->assertLayoutLoaded();
        $this->assertLayoutHandleLoaded('adminhtml_loginlog_grid');
        $this->assertLayoutHandleNotLoaded('default');
        $this->assertLayoutBlockCreated('vinaikopp.loginlog.grid');
        $this->assertLayoutBlockInstanceOf('vinaikopp.loginlog.grid', 'VinaiKopp_LoginLog_Block_Adminhtml_LoginLog_List_Grid');
    }

    /**
     * @covers ::deleteMassAction
     */
    public function testDeleteMassAction()
    {
        $this->app()->getRequest()->setPost('logins', array(1, 2, 3));
        $mockLogin = $this->getModelMock('vinaikopp_loginlog/login');
        $mockLogin->expects($this->exactly(3))
            ->method('delete');
        $mockLogin->expects($this->exactly(3))
            ->method('setId')
            ->will($this->returnSelf());
        $this->replaceByMock('model', 'vinaikopp_loginlog/login', $mockLogin);
        $this->dispatch('adminhtml/loginlog/deleteMass');
    }
    
    public function testExportCsvActionExists()
    {
        $this->assertTrue(
            is_callable(array($this->class, 'exportCsvAction')),
            "Method exportCsvAction does not exist on {$this->class}"
        );
    }

    /**
     * @depends testExportCsvActionExists
     * @covers ::exportCsvAction
     * @throws Exception
     * @throws Zend_Controller_Response_Exception
     */
    public function testExportCsvAction()
    {
        if (! function_exists('set_exit_overload')) {
            $this->markTestSkipped("PHP-test-helper extension with exit overload not available, skipping test.");
        }

        $this->assertTrue($this->app()->getResponse()->canSendHeaders());
        
        set_exit_overload(function() {});
        try {
            ob_start();
            $this->dispatch('adminhtml/loginlog/exportCsv');
            ob_end_clean();
        } catch (Zend_Controller_Response_Exception $e) {
            ob_end_clean();
            // Catch only headers already sent exception here
            if ($e->getMessage() != 'Cannot send headers; headers already sent') {
                throw $e;
            }
        }
        unset_exit_overload();
        
        $this->assertTrue(false !== $this->app()->getLayout()->getBlock('vinaikopp_loginlog.export'));
        $this->assertResponseHeaderContains('Content-Disposition', 'attachment; filename=');
        $this->assertResponseHeaderEquals('Content-Type', 'application/octet-stream');
    }
    
    public function testExportXmlActionExists()
    {
        $this->assertTrue(
            is_callable(array($this->class, 'exportXmlAction')),
            "Method exportXmlAction does not exist on {$this->class}"
        );
    }

    /**
     * @depends testExportXmlActionExists
     * @covers ::exportXmlAction
     * @throws Exception
     * @throws Zend_Controller_Response_Exception
     */
    public function testExportXmlAction()
    {
        if (! function_exists('set_exit_overload')) {
            $this->markTestSkipped("PHP-test-helper extension with exit overload not available, skipping test.");
        }
        
        $this->assertTrue($this->app()->getResponse()->canSendHeaders());
        
        set_exit_overload(function() {});
        try {
            ob_start();
            $this->dispatch('adminhtml/loginlog/exportXml');
            ob_end_clean();
        } catch (Zend_Controller_Response_Exception $e) {
            ob_end_clean();
            // Catch only headers already sent exception here
            if ($e->getMessage() != 'Cannot send headers; headers already sent') {
                throw $e;
            }
        }
        unset_exit_overload();
        
        $this->assertTrue(false !== $this->app()->getLayout()->getBlock('vinaikopp_loginlog.export'));
        $this->assertResponseHeaderContains('Content-Disposition', 'attachment; filename=');
        $this->assertResponseHeaderEquals('Content-Type', 'application/octet-stream');
    }

    public function testLookupActionExists()
    {
        $this->assertTrue(
            is_callable(array($this->class, 'lookupAction')),
            "Method lookupAction does not exist on {$this->class}"
        );
    }
    
    protected function getMockLookupData()
    {
        return array(
            'statusCode' => 'OK',
            'statusMessage' => '',
            'ipAddress' => '127.0.0.1',
            'countryCode' => 'DE',
            'countryName' => 'Germany',
            'regionName' => 'NVM',
            'cityName' => 'Test City',
            'zipCode' => '123456',
            'latitude' => '100.100',
            'longitude' => '-100.000'
        );
    }

    /**
     * @depends testLookupActionExists
     * @covers ::lookupAction
     */
    public function testLookupAction()
    {
        $mockCache = $this->getModelMock('core/cache');
        $mockCache->expects($this->any())
            ->method('load')
            ->will($this->returnValue(false));
        $this->replaceByMock('model', 'core/cache', $mockCache);
        
        $mockLookup = $this->getModelMock('vinaikopp_loginlog/ipInfoDb');
        $mockLookup->expects($this->once())
            ->method('lookupIp')
            ->will($this->returnValue($this->getMockLookupData()));
        $mockLookup->expects($this->any())
            ->method('isLookupAvailable')
            ->will($this->returnValue(true));
        $this->replaceByMock('model', 'vinaikopp_loginlog/ipInfoDb', $mockLookup);
        
        $mockLookup = $this->getModelMock('vinaikopp_loginlog/login', array('load', 'save', 'delete', 'getIp'));
        $mockLookup->expects($this->atLeastOnce())
            ->method('getIp')
            ->will($this->returnValue('127.0.0.1'));
        $this->replaceByMock('model', 'vinaikopp_loginlog/login', $mockLookup);
        
        
        $this->dispatch('adminhtml/loginlog/lookup', array('id' => 1));
        
        
        $this->assertLayoutHandleLoaded('default');
        $this->assertLayoutHandleLoaded('adminhtml_loginlog_lookup');
        
        // Check no redirect to 404
        $this->assertResponseHttpCode(200);
        
        $blockName = 'vinaikopp.loginlog.lookup';
        $this->assertLayoutBlockCreated($blockName);
        $block = $this->app()->getLayout()->getBlock($blockName);
        $this->assertTrue((bool) $block->getTemplate());
    }
} 