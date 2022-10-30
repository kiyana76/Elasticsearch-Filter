<?php

namespace Kiyana76\ElasticSearchFilter\Jobs;

use App\Mail\OrderExcelDownloadMail;
use Elasticsearch\ClientBuilder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Kiyana76\LaravelFilter\Filter;
use Box\Spout\Common\Type;
use Box\Spout\Writer\WriterFactory;

class GenerateExcelJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;


    private $client;
    private $params;
    private $resourceCollection;
    private $excelIgnoreColumns;
    private $excelPrefixFileName;

    private $userGuard;
    private $userId;
    private $mailFlag;
    private $totalCount;
    private $index;

    /**
     * Create a new job instance.
     *
     */
    public function __construct($params, $totalCount, $resourceCollection, $excelIgnoreColumns, $excelPrefixFileName, $userGuard, $userId, $mailFlag = false)
    {
        $this->params     = $params;
        $this->totalCount = $totalCount;

        $this->resourceCollection  = $resourceCollection;
        $this->excelIgnoreColumns  = $excelIgnoreColumns;
        $this->excelPrefixFileName = $excelPrefixFileName;

        $this->userGuard = $userGuard;
        $this->userId    = $userId;
        $this->mailFlag  = $mailFlag;
    }

    /**
     * Execute the job.
     *
     * @return string
     */
    public function handle()
    {
        auth()->shouldUse($this->userGuard);
        auth()->loginUsingId($this->userId);

        $this->setClient();

        if (!file_exists(base_path("storage/app/public/order/"))) {
            mkdir(base_path("storage/app/public/order/"));
        }

        $filename     = $this->excelPrefixFileName . date("Y-m-d") . "-" . Str::random(10) . '.xlsx';
        $filenamePath = base_path("storage/app/public/order/" . $filename);

        $writer = WriterFactory::create(Type::XLSX);
        $writer->setShouldUseInlineStrings(true)
            ->setTempFolder(sys_get_temp_dir())
            ->openToFile($filenamePath);

        // ------------------------------------ header ------------------------------------
        $this->setSizeParam(10000);
        $elasticRes = $this->client->search($this->params);
        $res        = $this->prepareResult($elasticRes);

        $row = $this->getResourceCollection($res)->jsonSerialize()[0];

        $header = [];
        foreach ($row as $key => $value) {
            if (is_array($value) || is_object($value))
                continue;

            if (in_array($key, $this->excelIgnoreColumns))
                continue;

            $header[] = trans("validation.attributes.{$key}");
        }
        $writer->addRow($header);

        // ------------------------------------ Rows ------------------------------------
        for ($i = 0; $i <= $this->totalCount; $i += 10000) {
            if ($i != 0)
                $this->getAndSetSearchAfterParams($elasticRes);
            $elasticRes = $this->client->search($this->params);
            $res        = $this->prepareResult($elasticRes);

            $rows = $this->getResourceCollection($res)->jsonSerialize();

            $sheetData = [];
            foreach ($rows as $row) {
                $datum = [];
                foreach ($row as $key => $value) {
                    if (is_array($value) || is_object($value))
                        continue;

                    if (in_array($key, $this->excelIgnoreColumns))
                        continue;

                    $datum[] = $value;
                }

                $sheetData[] = $datum;
            }

            $writer->addRows($sheetData);
        }

        $writer->close();

        if ($this->mailFlag) {
            $datum = explode('/', $filenamePath);
            $data  = [
                'file' => last($datum),
            ];

            Mail::to(auth()->user()->email)->send(new OrderExcelDownloadMail($data));
        }

        return $filenamePath;
    }

    public function getResourceCollection($rows)
    {
        if ($this->resourceCollection) {
            return $this->resourceCollection::collection($rows);
        }

        return false;
    }

    private function setSizeParam(int $size)
    {
        $this->params['size'] = $size;
    }

    private function prepareResult($elasticResults): array
    {
        $result = [];
        foreach ($elasticResults['hits']['hits'] as $elasticResult) {
            $result[] = $elasticResult['_source'];
        }

        return $result;
    }

    private function setClient()
    {
        $host         = config('database.elastic_search.host');
        $this->client = ClientBuilder::create()->setHosts([$host])->build();
    }

    private function getAndSetSearchAfterParams($result) {
        $total = count($result['hits']['hits']);
        $searchAfterParams = $result['hits']['hits'][$total - 1]['sort'];

        $this->params['body']['search_after'] = $searchAfterParams;
        return;
    }
}
