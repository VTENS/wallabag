<?php

namespace Tests\Wallabag\CoreBundle\Controller;

use Tests\Wallabag\CoreBundle\WallabagCoreTestCase;

class RssControllerTest extends WallabagCoreTestCase
{
    public function validateDom($xml, $type, $nb = null)
    {
        $doc = new \DOMDocument();
        $doc->loadXML($xml);

        $xpath = new \DOMXpath($doc);

        if (null === $nb) {
            $this->assertGreaterThan(0, $xpath->query('//item')->length);
        } else {
            $this->assertEquals($nb, $xpath->query('//item')->length);
        }

        $this->assertEquals(1, $xpath->query('/rss')->length);
        $this->assertEquals(1, $xpath->query('/rss/channel')->length);

        $this->assertEquals(1, $xpath->query('/rss/channel/title')->length);
        $this->assertEquals('wallabag — '.$type.' feed', $xpath->query('/rss/channel/title')->item(0)->nodeValue);

        $this->assertEquals(1, $xpath->query('/rss/channel/pubDate')->length);

        $this->assertEquals(1, $xpath->query('/rss/channel/generator')->length);
        $this->assertEquals('wallabag', $xpath->query('/rss/channel/generator')->item(0)->nodeValue);

        $this->assertEquals(1, $xpath->query('/rss/channel/description')->length);
        $this->assertEquals('wallabag '.$type.' elements', $xpath->query('/rss/channel/description')->item(0)->nodeValue);

        $this->assertEquals(1, $xpath->query('/rss/channel/link[@rel="self"]')->length);
        $this->assertContains($type.'.xml', $xpath->query('/rss/channel/link[@rel="self"]')->item(0)->getAttribute('href'));

        $this->assertEquals(1, $xpath->query('/rss/channel/link[@rel="last"]')->length);
        $this->assertContains($type.'.xml?page=', $xpath->query('/rss/channel/link[@rel="last"]')->item(0)->getAttribute('href'));

        foreach ($xpath->query('//item') as $item) {
            $this->assertEquals(1, $xpath->query('title', $item)->length);
            $this->assertEquals(1, $xpath->query('source', $item)->length);
            $this->assertEquals(1, $xpath->query('link', $item)->length);
            $this->assertEquals(1, $xpath->query('guid', $item)->length);
            $this->assertEquals(1, $xpath->query('pubDate', $item)->length);
            $this->assertEquals(1, $xpath->query('description', $item)->length);
        }
    }

    public function dataForBadUrl()
    {
        return [
            [
                '/admin/YZIOAUZIAO/unread.xml',
            ],
            [
                '/wallace/YZIOAUZIAO/starred.xml',
            ],
            [
                '/wallace/YZIOAUZIAO/archives.xml',
            ],
        ];
    }

    /**
     * @dataProvider dataForBadUrl
     */
    public function testBadUrl($url)
    {
        $client = $this->getClient();

        $client->request('GET', $url);

        $this->assertEquals(404, $client->getResponse()->getStatusCode());
    }

    public function testUnread()
    {
        $client = $this->getClient();
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $user = $em
            ->getRepository('WallabagUserBundle:User')
            ->findOneByUsername('admin');

        $config = $user->getConfig();
        $config->setRssToken('SUPERTOKEN');
        $config->setRssLimit(2);
        $em->persist($config);
        $em->flush();

        $client->request('GET', '/admin/SUPERTOKEN/unread.xml');

        $this->assertEquals(200, $client->getResponse()->getStatusCode());

        $this->validateDom($client->getResponse()->getContent(), 'unread', 2);
    }

    public function testStarred()
    {
        $client = $this->getClient();
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $user = $em
            ->getRepository('WallabagUserBundle:User')
            ->findOneByUsername('admin');

        $config = $user->getConfig();
        $config->setRssToken('SUPERTOKEN');
        $config->setRssLimit(1);
        $em->persist($config);
        $em->flush();

        $client = $this->getClient();
        $client->request('GET', '/admin/SUPERTOKEN/starred.xml');

        $this->assertEquals(200, $client->getResponse()->getStatusCode(), 1);

        $this->validateDom($client->getResponse()->getContent(), 'starred');
    }

    public function testArchives()
    {
        $client = $this->getClient();
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $user = $em
            ->getRepository('WallabagUserBundle:User')
            ->findOneByUsername('admin');

        $config = $user->getConfig();
        $config->setRssToken('SUPERTOKEN');
        $config->setRssLimit(null);
        $em->persist($config);
        $em->flush();

        $client = $this->getClient();
        $client->request('GET', '/admin/SUPERTOKEN/archive.xml');

        $this->assertEquals(200, $client->getResponse()->getStatusCode());

        $this->validateDom($client->getResponse()->getContent(), 'archive');
    }

    public function testPagination()
    {
        $client = $this->getClient();
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $user = $em
            ->getRepository('WallabagUserBundle:User')
            ->findOneByUsername('admin');

        $config = $user->getConfig();
        $config->setRssToken('SUPERTOKEN');
        $config->setRssLimit(1);
        $em->persist($config);
        $em->flush();

        $client = $this->getClient();

        $client->request('GET', '/admin/SUPERTOKEN/unread.xml');
        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $this->validateDom($client->getResponse()->getContent(), 'unread');

        $client->request('GET', '/admin/SUPERTOKEN/unread.xml?page=2');
        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $this->validateDom($client->getResponse()->getContent(), 'unread');

        $client->request('GET', '/admin/SUPERTOKEN/unread.xml?page=3000');
        $this->assertEquals(302, $client->getResponse()->getStatusCode());
    }
}
