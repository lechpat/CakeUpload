<?php
namespace Upload\Controller;

use Upload\Controller\AppController;

/**
 * Handler Controller
 *
 * @property \Upload\Model\Table\HandlerTable $Handler
 */
class HandlerController extends AppController
{

    public function initialize()
    {
        parent::initialize();
        $this->loadComponent('Upload.Uploader');
    }

    /**
     * Index method
     *
     * @return void
     */
    public function index()
    {
        $this->set('handler', []);
        $this->set('_serialize', ['handler']);
    }

}
