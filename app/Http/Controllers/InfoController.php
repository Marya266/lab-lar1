<?php

namespace App\Http\Controllers;

use App\DTOs\PhpInfoDTO;
use Illuminate\Http\Request;
use App\DTOs\ClientDataDTO;
use App\DTOs\DatabaseInfoDTO;
use Illuminate\Support\Facades\DB;



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

}


