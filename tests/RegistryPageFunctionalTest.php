<?php

namespace SilverStripe\Registry\Tests;

use SilverStripe\Control\Controller;
use SilverStripe\Dev\CSSContentParser;
use SilverStripe\Dev\FunctionalTest;
use SilverStripe\Registry\Tests\Stub\RegistryPageTestContact;
use SilverStripe\Registry\Tests\Stub\RegistryPageTestContactExtra;
use SilverStripe\Registry\Tests\Stub\RegistryPageTestPage;

class RegistryPageFunctionalTest extends FunctionalTest
{
    protected static $fixture_file = [
        'fixtures/RegistryPageFunctionalTest.yml',
        'fixtures/RegistryPageTestContact.yml'
    ];

    protected static $extra_dataobjects = [
        RegistryPageTestContact::class,
        RegistryPageTestContactExtra::class,
        RegistryPageTestPage::class
    ];

    protected static $use_draft_site = true;

    public function testUseLink()
    {
        // Page with links
        $page = $this->objFromFixture(RegistryPageTestPage::class, 'contact-registrypage-extra');
        $response = $this->get($page->Link());
        $parser = new CSSContentParser($response->getBody());

        $cells = $parser->getBySelector('table.results tbody tr td');

        $this->assertContains('/contact-search-extra/', (string) $cells[0]->a->attributes()->href[0]);
    }

    public function testFilteredSearchResults()
    {
        $page = $this->objFromFixture(RegistryPageTestPage::class, 'contact-registrypage');
        $uri = Controller::join_links(
            $page->RelativeLink('RegistryFilterForm'),
            '?' .
            http_build_query(array(
                'FirstName' => 'Alexander',
                'action_doRegistryFilter' => 'Filter'
            ))
        );
        $response = $this->get($uri);

        $parser = new CSSContentParser($response->getBody());
        $rows = $parser->getBySelector('table.results tbody tr');

        $cells = $rows[0]->td;

        $this->assertCount(1, $rows);
        $this->assertEquals('Alexander', (string) $cells[0]);
        $this->assertEquals('Bernie', (string) $cells[1]);
    }

    public function testFilteredByRelationSearchResults()
    {
        $page = $this->objFromFixture(RegistryPageTestPage::class, 'contact-registrypage-extra');
        $uri = Controller::join_links(
            $page->RelativeLink('RegistryFilterForm'),
            '?' . http_build_query(array(
                'RegistryPage.Title' => $page->Title,
                'action_doRegistryFilter' => 'Filter'
            ))
        );

        $response = $this->get($uri);

        $parser = new CSSContentParser($response->getBody());

        $rows = $parser->getBySelector('table.results tbody tr');
        $cells = $rows[0]->td;

        $this->assertCount(1, $rows);
        $this->assertEquals('Jimmy', (string) $cells[0]->a[0]);
        $this->assertEquals('Sherson', (string) $cells[1]->a[0]);
    }

    public function testUserCustomSummaryField()
    {
        $page = $this->objFromFixture(RegistryPageTestPage::class, 'contact-registrypage-extra');
        $response = $this->get($page->Link());
        $parser = new CSSContentParser($response->getBody());

        $cells = $parser->getBySelector('table.results tbody tr td');

        $this->assertContains($page->getDataSingleton()->getStaticReference(), (string) $cells[3]->a[0]);
    }

    public function testSearchResultsLimitAndStart()
    {
        $page = $this->objFromFixture(RegistryPageTestPage::class, 'contact-registrypage-limit');
        $uri = Controller::join_links(
            $page->RelativeLink('RegistryFilterForm'),
            '?' . http_build_query(array(
                'Sort' => 'FirstName',
                'Dir' => 'DESC',
                'action_doRegistryFilter' => 'Filter'
            ))
        );


        $response = $this->get($uri);

        $parser = new CSSContentParser($response->getBody());
        $rows = $parser->getBySelector('table.results tbody tr');
        $anchors = $parser->getBySelector('ul.pageNumbers li a');

        $this->assertCount(3, $rows, 'Limited to 3 search results');
        $this->assertCount(4, $anchors, '4 paging anchors, including next');

        $this->assertContains('Sort=FirstName', (string) $anchors[0]['href']);
        $this->assertContains('Dir=DESC', (string) $anchors[0]['href']);

        $this->assertContains('start=0', (string) $anchors[0]['href']);
        $this->assertContains('start=3', (string) $anchors[1]['href']);
        $this->assertContains('start=6', (string) $anchors[2]['href']);
    }

    public function testGetParamsPopulatesSearchForm()
    {
        $page = $this->objFromFixture(RegistryPageTestPage::class, 'contact-registrypage');
        $uri = Controller::join_links(
            $page->RelativeLink('RegistryFilterForm'),
            '?' . http_build_query(array(
                'FirstName' => 'Alexander',
                'Sort' => 'FirstName',
                'Dir' => 'DESC',
                'action_doRegistryFilter' => 'Filter'
            ))
        );
        $response = $this->get($uri);

        $parser = new CSSContentParser($response->getBody());
        $firstNameField = $parser->getBySelector('#Form_RegistryFilterForm_FirstName');
        $sortField = $parser->getBySelector('#Form_RegistryFilterForm_Sort');
        $dirField = $parser->getBySelector('#Form_RegistryFilterForm_Dir');

        $this->assertEquals('Alexander', (string) $firstNameField[0]['value']);
        $this->assertEquals('FirstName', (string) $sortField[0]['value']);
        $this->assertEquals('DESC', (string) $dirField[0]['value']);
    }

    public function testQueryLinks()
    {
        $page = $this->objFromFixture(RegistryPageTestPage::class, 'contact-registrypage');
        $uri = Controller::join_links(
            $page->RelativeLink('RegistryFilterForm'),
            '?' . http_build_query(array(
                'FirstName' => 'Alexander',
                'action_doRegistryFilter' => 'Filter'
            ))
        );
        $response = $this->get($uri);

        $parser = new CSSContentParser($response->getBody());
        $rows = $parser->getBySelector('table.results thead tr');
        $anchors = $rows[0]->th->a;

        $this->assertContains('FirstName=Alexander', (string) $anchors[0]['href']);
        $this->assertContains('Surname=', (string) $anchors[0]['href']);
        $this->assertContains('Sort=FirstName', (string) $anchors[0]['href']);
        $this->assertContains('Dir=ASC', (string) $anchors[0]['href']);
        $this->assertContains('action_doRegistryFilter=Filter', (string) $anchors[0]['href']);
    }

    public function testShowExistingRecord()
    {
        $record = $this->objFromFixture(RegistryPageTestContact::class, 'alexander');
        $page = $this->objFromFixture(RegistryPageTestPage::class, 'contact-registrypage');
        $response = $this->get(Controller::join_links($page->RelativeLink(), 'show', $record->ID));

        $this->assertContains('Alexander Bernie', $response->getBody());
    }

    public function testPageNotFoundNonExistantRecord()
    {
        $page = $this->objFromFixture(RegistryPageTestPage::class, 'contact-registrypage');
        $response = $this->get(Controller::join_links($page->RelativeLink(), 'show', '123456'));
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testColumnName()
    {
        $page = $this->objFromFixture(RegistryPageTestPage::class, 'contact-registrypage');
        $uri = Controller::join_links(
            $page->RelativeLink('RegistryFilterForm'),
            '?' . http_build_query(array(
                'action_doRegistryFilter' => 'Filter'
            ))
        );
        $response = $this->get($uri);

        $parser = new CSSContentParser($response->getBody());
        $rows = $parser->getBySelector('table.results thead tr');
        $anchors = $rows[0]->th->a;

        $this->assertEquals('First name', (string) $anchors[0]);
    }

    public function testSortableColumns()
    {
        $page = $this->objFromFixture(RegistryPageTestPage::class, 'contact-registrypage-extra');
        $response = $this->get($page->Link());
        $parser = new CSSContentParser($response->getBody());
        $columns = $parser->getBySelector('table.results thead tr th');

        $this->assertNotEmpty($columns[0]->a);
        $this->assertNotEmpty($columns[1]->a);
        $this->assertNotEmpty($columns[2]->a);
        $this->assertEquals('Other', $columns[3]);
    }

    public function testExportLink()
    {
        $page = $this->objFromFixture(RegistryPageTestPage::class, 'contact-registrypage');
        $uri = Controller::join_links(
            $page->RelativeLink('RegistryFilterForm'),
            '?' . http_build_query(array(
                'FirstName' => 'Alexander',
                'Sort' => 'FirstName',
                'Dir' => 'DESC',
                'action_doRegistryFilter' => 'Filter'
            ))
        );
        $response = $this->get($uri);

        $parser = new CSSContentParser($response->getBody());
        $anchor = $parser->getBySelector('a.export');

        $this->assertContains('export?', (string) $anchor[0]['href']);
        $this->assertContains('FirstName=Alexander', (string) $anchor[0]['href']);
        $this->assertContains('Surname=', (string) $anchor[0]['href']);
        $this->assertContains('Sort=FirstName', (string) $anchor[0]['href']);
        $this->assertContains('Dir=DESC', (string) $anchor[0]['href']);
        $this->assertContains('action_doRegistryFilter=Filter', (string) $anchor[0]['href']);
    }
}
