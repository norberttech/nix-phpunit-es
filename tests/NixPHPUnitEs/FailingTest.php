<?php

namespace NixPHPUnitEs;

use Elasticsearch\ClientBuilder;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

class FailingTest extends TestCase
{
    /**
     * @dataProvider data_provider
     */
    public function test_es(string $id, array $data, $index) : void
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

        fwrite(STDOUT, "$index - " . Memory::toString(memory_get_usage(true)) . " \n");
    }

    public function data_provider() : \Generator
    {
        $total = 0;
        for ($i = 0; $i < 2500; $i++) {
            $entries = 1;
            for ($d = 0; $d < $entries; $d++) {
                yield [
                    Uuid::uuid4()->toString(),
                    [
                        'id' => \random_int(0, 1000000),
                        'type' => 'TYPE_' . \random_int(0, 1000),
                        'text_01' => \uniqid('text_01', false)
                    ],
                    $total
                ];

                $total++;
            }
        }
    }
}