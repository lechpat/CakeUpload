<?php
namespace Upload\Test\TestCase\Model\Behavior;

use Cake\TestSuite\TestCase;
use Upload\Model\Behavior\AttachmentBehavior;

/**
 * Upload\Model\Behavior\AttachmentBehavior Test Case
 */
class AttachmentBehaviorTest extends TestCase
{

    /**
     * setUp method
     *
     * @return void
     */
    public function setUp()
    {
        parent::setUp();
        $this->Attachment = new AttachmentBehavior();
    }

    /**
     * tearDown method
     *
     * @return void
     */
    public function tearDown()
    {
        unset($this->Attachment);

        parent::tearDown();
    }

    /**
     * Test initial setup
     *
     * @return void
     */
    public function testInitialization()
    {
        $this->markTestIncomplete('Not implemented yet.');
    }
}
