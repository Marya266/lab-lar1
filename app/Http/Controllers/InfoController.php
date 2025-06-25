<?php

namespace App\Http\Controllers;

use App\DTOs\PhpInfoDTO;
use Illuminate\Http\Request;
use App\DTOs\ClientDataDTO;
use App\DTOs\DatabaseInfoDTO;
use Illuminate\Support\Facades\DB;
use App\Jobs\GenerateReportJob;

class InfoController extends Controller
{
    public function phpInfo(){
        $dto=new PhpInfoDTO([
            'version' => phpversion(),
            'extensions' => get_loaded_extensions(),
            'server_info' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'
        ]);
          return response()->json($dto->toArray());
   }

   public function clientData(Request $request)
    {
        $dto = new ClientDataDTO([
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'headers' => $request->headers->all(),
            'parameters' => $request->all()
        ]);
        
        return response()->json($dto->toArray());
    }

        public function databaseInfo()
    {
        try {
            $connection = DB::connection();
            $dto = new DatabaseInfoDTO([
                'driver' => $connection->getDriverName(),
                'database' => $connection->getDatabaseName(),
                'version' => DB::select('SELECT VERSION() as version')[0]->version,
                'charset' => config('database.connections.mysql.charset'),
                'tables_count' => count(DB::select('SHOW TABLES'))
            ]);
        } catch (\Exception $e) {
            $dto = new DatabaseInfoDTO([
                'error' => 'Database connection failed: ' . $e->getMessage()
            ]);
        }
        
        return response()->json($dto->toArray());
    }

    public function generateReport(Request $request)
    {
        $reportType = $request->input('type', 'php_info');
        $format = $request->input('format', 'json');
        
        $validTypes = ['php_info', 'database_info', 'client_data'];
        $validFormats = ['json', 'xml', 'csv', 'txt'];
        
        if (!in_array($reportType, $validTypes)) {
            return response()->json(['error' => 'Неверный тип отчёта'], 400);
        }
        
        if (!in_array($format, $validFormats)) {
            return response()->json(['error' => 'Неверный формат'], 400);
        }
        
        // Подготавливаем данные запроса для client_data
        $requestData = null;
        if ($reportType === 'client_data') {
            $requestData = [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'method' => $request->method(),
                'url' => $request->fullUrl(),
                'headers' => $request->headers->all(),
                'parameters' => $request->all()
            ];
        }
        
        // Запускаем задачу в фоне
        GenerateReportJob::dispatch($reportType, $format, $requestData);
        
        return response()->json([
            'message' => 'Задача на создание отчёта поставлена в очередь',
            'type' => $reportType,
            'format' => $format,
            'status' => 'queued'
        ]);
    }

    public function generateReportSync(Request $request)
    {
        $reportType = $request->input('type', 'php_info');
        $format = $request->input('format', 'json');
        
        $validTypes = ['php_info', 'database_info', 'client_data'];
        $validFormats = ['json', 'xml', 'csv', 'txt'];
        
        if (!in_array($reportType, $validTypes)) {
            return response()->json(['error' => 'Неверный тип отчёта'], 400);
        }
        
        if (!in_array($format, $validFormats)) {
            return response()->json(['error' => 'Неверный формат'], 400);
        }
        
        $requestData = null;
        if ($reportType === 'client_data') {
            $requestData = [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'method' => $request->method(),
                'url' => $request->fullUrl(),
                'headers' => $request->headers->all(),
                'parameters' => $request->all()
            ];
        }
        
        // Выполняем задачу синхронно
        $job = new GenerateReportJob($reportType, $format, $requestData);
        $job->handle();
        
        return response()->json([
            'message' => 'Отчёт создан успешно',
            'type' => $reportType,
            'format' => $format,
            'status' => 'completed'
        ]);
    }

}


