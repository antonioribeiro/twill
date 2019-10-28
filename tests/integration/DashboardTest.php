<?php

namespace A17\Twill\Tests\Integration;

class DashboardTest extends ModulesTestBase
{
    public function setup(): void
    {
        parent::setUp();

        $this->loadConfig('{$stubs}/modules/dashboard/twill.php'); /// commenting this line it start working
    }

    public function testCanDisplayDashboard()
    {
        $this->request('/twill')->assertStatus(200);

        $this->assertSee('Personnel');
        $this->assertSee('Categories');

        $this->request('/twill/personnel/authors')->assertStatus(200);

        $this->assertSee('Name');
        $this->assertSee('Languages');
        $this->assertSee('Mine');
        $this->assertSee('Add new');

        $this->request('/twill/categories')->assertStatus(200);
    }

    public function testCanSearchString()
    {
        $this->createAuthor(3);

        $this->ajax("/twill/search?search={$this->name_en}")->assertStatus(200);

        $this->assertJson($this->content());

        $result = json_decode($this->content(), true);

        $this->assertEquals(
            $this->now->format('Y-m-d\TH:i:s+00:00'),
            $result[0]['date']
        );
    }
}
