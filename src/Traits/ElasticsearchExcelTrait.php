<?php

namespace Kiyana76\ElasticSearchFilter\Traits;

use Box\Spout\Common\Type;
use Box\Spout\Writer\WriterFactory;
use Illuminate\Support\Str;
use Kiyana76\ElasticSearchFilter\Jobs\GenerateExcelJob;

trait ElasticsearchExcelTrait
{

    public function excel($params)
    {
        $elasticRes = $this->client->search($params);
        $totalCount = $elasticRes['hits']['total']['value'];

        if ($totalCount == 0) {
            return [
                'success' => true,
                'message' => 'رکوردی یافت نشد.'
            ];
        }
        else if ($totalCount > 10000) {
            dispatch(new GenerateExcelJob(
                $params,
                $totalCount,
                $this->resourceCollection,
                $this->excelIgnoreColumns,
                $this->excelPrefixFileName,
                auth()->user()->kind,
                auth()->id(),
                true));
            return [
                'success' => true,
                'message' => 'اکسل برای شما ایمیل خواهد شد.'
            ];
        } else {
            $filePath = dispatch_now(new GenerateExcelJob(
                $params,
                $totalCount,
                $this->resourceCollection,
                $this->excelIgnoreColumns,
                $this->excelPrefixFileName,
                auth()->user()->kind,
                auth()->id(),
                false));
            $filename = $this->excelPrefixFileName . date("Y-m-d H:i:s") . '.xlsx';

            return response()->download($filePath, $filename);

        }

    }

    public function aggsExcel($collectionResource) {
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
        $row = $collectionResource->jsonSerialize()[0];

        $header = [];
        foreach ($row as $key => $value) {
            if (is_array($value) || is_object($value))
                continue;

            if (in_array($key, $this->excelIgnoreColumns))
                continue;

            $header[] = trans("validation.attributes.{$key}");
        }
        $writer->addRow($header);

        // ----------------------------------- rows -------------------------------------------
        $rows = $collectionResource->jsonSerialize();
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
        $writer->close();

        $filename = $this->excelPrefixFileName . date("Y-m-d H:i:s") . '.xlsx';
        return response()->download($filenamePath, $filename);
    }
}
