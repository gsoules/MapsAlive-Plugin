<?php

class MapsAlive_IndexController extends Omeka_Controller_AbstractActionController
{
    public function livedataAction()
    {
        $this->getResponse()->setHeader('Content-Type', 'application/json');
        $this->getResponse()->setHeader('Access-Control-Allow-Origin', '*');
        $request = new LiveDataRequest();
        $response = $request->handleLiveDataRequest();
        $this->view->response = $response;
    }
}
