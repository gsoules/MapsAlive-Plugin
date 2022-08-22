<?php

class MapsAlive_IndexController extends Omeka_Controller_AbstractActionController
{
    protected function emitHeaders()
    {
        $this->getResponse()->setHeader('Content-Type', 'application/json');
        $this->getResponse()->setHeader('Access-Control-Allow-Origin', '*');
    }

    public function findAction()
    {
        $this->emitHeaders();

        // Perform a SQL query using the query string arguments. The query returns all the matching items.
        $this->performQueryUsingSql();

        // Create JSON text for an array of the item Identifiers for the matching items.
        $request = new LiveDataRequest();
        $response = $request->handleFindRequest($this->records);

        $this->view->response = $response;
    }

    public function livedataAction()
    {
        $this->emitHeaders();

        $request = new LiveDataRequest();
        $response = $request->handleLiveDataRequest();

        $this->view->response = $response;
    }

    protected function performQueryUsingSql()
    {
        // Set up a search request the same way as done by AvantSearch for an advanced search.
        $viewId = SearchResultsViewFactory::TABLE_VIEW_ID;
        $searchResultsView = SearchResultsViewFactory::createSearchResultsView($viewId);
        $this->getRequest()->setParamSources(array('_GET'));
        $params = $this->getAllParams();
        $params['results'] = $searchResultsView;

        // Fool the search mechanism into thinking the request is coming from AvantSearch.
        $params['module'] = "avant-search";

        try
        {
            // Perform the query.
            $this->_helper->db->setDefaultModelName('Item');
            $this->records = $this->_helper->db->findBy($params, 10000, 1);
            $this->totalRecords = $this->_helper->db->count($params);
        }
        catch (Exception $e)
        {
            $this->totalRecords = 0;
            $this->records = array();
            $searchResultsView->setSearchErrorCodeAndMessage(2, $e->getMessage());
        }
    }
}
