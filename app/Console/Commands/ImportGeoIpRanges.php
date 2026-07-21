<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

// 로그인 국가 변경 감지 기능(LoginCountryAlertService)이 쓰는 국가별 IP 대역 정적 데이터를 채운다.
// 데이터 출처: db-ip.com IP to Country Lite(CC BY 4.0, https://db-ip.com), sapics/ip-location-db 저장소를
// 통해 가입/키 없이 내려받음(database/geoip/dbip-country-ipv4.csv). 크론이 없는 환경이라 최초 1회 실행 후,
// 정확도를 유지하려면 관리자가 가끔 파일을 새로 받아 이 명령을 재실행하면 된다(자동 갱신 없음).
class ImportGeoIpRanges extends Command
{
    protected $signature = 'geoip:import {path=database/geoip/dbip-country-ipv4.csv}';

    protected $description = '국가별 IP 대역(IPv4) 정적 데이터를 ip_country_ranges 테이블로 가져옵니다';

    public function handle(): int
    {
        $path = base_path($this->argument('path'));

        if (! file_exists($path)) {
            $this->error("파일을 찾을 수 없습니다: {$path}");

            return self::FAILURE;
        }

        $handle = fopen($path, 'r');

        if ($handle === false) {
            $this->error("파일을 열 수 없습니다: {$path}");

            return self::FAILURE;
        }

        $this->info('기존 데이터를 삭제합니다...');
        DB::table('ip_country_ranges')->truncate();

        $this->info('가져오는 중...');

        $batch = [];
        $total = 0;

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) < 3) {
                continue;
            }

            [$startIp, $endIp, $countryCode] = $row;
            $start = ip2long($startIp);
            $end = ip2long($endIp);

            if ($start === false || $end === false) {
                continue;
            }

            $batch[] = ['ip_start' => $start, 'ip_end' => $end, 'country_code' => $countryCode];

            if (count($batch) >= 1000) {
                DB::table('ip_country_ranges')->insert($batch);
                $total += count($batch);
                $batch = [];
                $this->output->write("\r{$total}건 처리됨");
            }
        }

        if ($batch !== []) {
            DB::table('ip_country_ranges')->insert($batch);
            $total += count($batch);
        }

        fclose($handle);

        $this->newLine();
        $this->info("완료: 총 {$total}건을 가져왔습니다.");

        return self::SUCCESS;
    }
}
