<?php

namespace NixPHPUnitEs;

use Elasticsearch\ClientBuilder;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

class PassingTest extends TestCase
{
    public function test_es() : void
    {
        $client = ClientBuilder::create()
            ->setRetries(0)
            ->setHosts(['localhost'])
            ->setConnectionParams([
                'client' => [
                    'curl' => [
                        CURLOPT_TIMEOUT => 1,
                        CURLOPT_CONNECTTIMEOUT => 1
                    ]
                ]
            ])
            ->setHandler(ClientBuilder::singleHandler())
            ->build();

        $total = 0;
        for ($i = 0; $i < 10000; $i++) {
            $entries = 1;
            for ($d = 0; $d < $entries; $d++) {
                $id = Uuid::uuid4()->toString();
                $data = [
                    'id' => \random_int(0, 1000000),
                    'type' => 'TYPE_' . \random_int(0, 1000),
                    'integer_01' => \random_int(0, 1000),
                    'text_01' => \uniqid('text_01', false)
                ];

                $client->index([
                    'index' => Indexes::STRICT_MAPPING_INDEX,
                    'refresh' => true,
                    'id' => $id,
                    'body' => $data
                ]);

                $document = $client->get(
                    [
                        'index' => Indexes::STRICT_MAPPING_INDEX,
                        'id' => $id,
                    ]
                );

                $this->assertSame($id, $document['_id']);

                $client->deleteByQuery([
                    'index' => Indexes::STRICT_MAPPING_INDEX,
                    'conflicts' => 'proceed',
                    'refresh' => true,
                    'body' => [
                        'query' => [
                            'match_all' => (object) [], // https://github.com/elastic/elasticsearch-php/issues/495#issuecomment-258533457
                        ],
                    ],
                ]);

                $total++;

                fwrite(STDOUT, "$total - " . Memory::toString(memory_get_usage(true)) . " \n");
            }
        }

        $this->assertTrue(true);
    }
}