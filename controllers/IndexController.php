<?php

class MapsAlive_IndexController extends Omeka_Controller_AbstractActionController
{
    public function htmlAction()
    {
        $this->getResponse()->setHeader('Content-Type', 'text/html; charset=utf-8');
        $this->performAction(LiveDataRequest::REQUEST_TYPE_HTML);
    }

    public function jsonAction()
    {
        $this->getResponse()->setHeader('Content-Type', 'application/json');
        $this->performAction(LiveDataRequest::REQUEST_TYPE_JSON);
    }

    private function performAction($requestType)
    {
        $this->getResponse()->setHeader('Access-Control-Allow-Origin', '*');
        $request = new LiveDataRequest();
        $response = $request->handleLiveDataRequest($requestType);
        $this->view->response = $response;
    }
}
