<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

use Illuminate\Database\QueryException;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class InstallController extends Controller
{
    public function do_install(Request $request)
    {
        try {
            $dbConfig = config('database');
            switch ($request['db_type']) {
                case 'mysql':
                    $mysqlDB                          = [
                        'host'     => $request->input('db_host'),
                        'port'     => $request->input('db_port'),
                        'database' => $request->input('db_database'),
                        'username' => $request->input('db_username'),
                        'password' => $request->input('db_password'),
                    ];
                    $dbConfig['connections']['mysql'] = array_merge($dbConfig['connections']['mysql'], $mysqlDB);
                    config(['database' => $dbConfig]);
                    $postData = [
                        'title'       => $request['title'],
                        'app_url'     => $request['app_url'],
                        'admin_path'  => $request['admin_path'],
                        'db_type'     => $request['db_type'],
                        'db_host'     => $request['db_host'],
                        'db_port'     => $request['db_port'],
                        'db_database' => $request['db_database'],
                        'db_username' => $request['db_username'],
                        'db_password' => $request['db_password']
                    ];
                    break;
                case 'sqlite':
                    if (file_exists(database_path('database.sqlite'))) unlink(database_path('database.sqlite'));
                    file_put_contents(database_path('database.sqlite'), '');
                    $dbConfig['default']               = 'sqlite';
                    $dbConfig['connections']['sqlite'] = array_merge($dbConfig['connections']['sqlite'], [
                        'database' => database_path('database.sqlite')
                    ]);
                    config(['database' => $dbConfig]);
                    $postData = [
                        'title'       => $request['title'],
                        'app_url'     => $request['app_url'],
                        'admin_path'  => $request['admin_path'],
                        'db_type'     => $request['db_type'],
                        'db_host'     => "",
                        'db_port'     => "",
                        'db_database' => database_path('database.sqlite'),
                        'db_username' => "",
                        'db_password' => ""
                    ];
                    break;
                default:
                    return ResponseController::response(400, '数据库类型错误');
            }

            DB::purge();
            // db测试
            DB::connection()->select('select 1 limit 1');

            // 获得文件模板
            $envExamplePath = base_path() . DIRECTORY_SEPARATOR . '.env.example';
            $envPath        = base_path() . DIRECTORY_SEPARATOR . '.env';
            $installLock    = base_path() . DIRECTORY_SEPARATOR . 'install.lock';
            $installSql     = database_path() . DIRECTORY_SEPARATOR . 'sql' . DIRECTORY_SEPARATOR . $request['db_type'] . '.sql';
            $envTemp        = file_get_contents($envExamplePath);

            // 临时写入key
            $postData['app_key'] = 'base64:' . base64_encode(Encrypter::generateKey(config('app.cipher')));

            foreach ($postData as $key => $item) {
                $envTemp = str_replace('{' . $key . '}', $item, $envTemp);
            }

            // 写入配置
            file_put_contents($envPath, $envTemp);
            // 导入sql
            DB::unprepared(file_get_contents($installSql));

            // 添加用户
            User::insert([
                'username' => 'admin',
                'password' => Hash::make('admin'),
                'is_admin' => 1,
            ]);

            // 写入安装锁
            file_put_contents($installLock, 'install ok');
            return ResponseController::response(200, '安装成功');
        } catch (QueryException $exception) {
            return ResponseController::response(400, '数据库配置错误 :' . $exception->getMessage());
        } catch (\Exception $exception) {
            return ResponseController::response(400, $exception->getMessage());
        }
    }
}
