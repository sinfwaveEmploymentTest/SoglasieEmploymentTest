<?php

namespace App\Http\Middleware;

use Redis;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Contracts\Validation\Validator;

/**
 * Static middleware helpers for open api test implementation
 */
class OpenApiRequestsHelpers
{
	
	/**
	 * Enable\Disable maintenance mode in open api
	 * if enable (true) send 500 error, service unavailable and exit flow
	 * 
	 * @param bool $enable true or false
	 * @return void
	 */
	public static function MaintenanceModeCheck( bool $enable )
	{
		
		if ( $enable )
		{
			
			response()->json(['status' => '500', 'message' => 'Cервис временно недоступен (произошла ошибка)'], 500)->send();
			exit;
			
		}
		
	}
	
	/**
	 * Validates incoming http request by accepted method and content
	 * if validation failed show 404 error
	 * 
	 * @param Illuminate\Http\Request $Request Laravel request object
	 * @param string $ValidMethod 'POST', 'GET', etc.
	 * @param array $ValidContent 'application/json', etc.
	 * @return void
	 */
	public static function ValidateRequestByType(Request $Request, string $ValidMethod, string $ValidContent)
	{
		
		$RequestMethod = $Request->method();
		$RequestAccepts = $Request->accepts($ValidContent);
		
		if ( $RequestMethod !== $ValidMethod || !$RequestAccepts )
		{
			
			abort(404);
			exit;
			
		}
		
	}
	
	/**
	 * SubValidator of standard validator fail and count of incoming json parameters
	 * if validation failed send 400 json response and stop
	 * 
	 * @param Illuminate\Http\Request $Request Laravel request object
	 * @param Illuminate\Contracts\Validation\Validator $Validator Laravel validation object
	 * @param int $Count number of parameters (1 or 2 or 3, etc.)
	 * @return void
	 */
	public static function SubValidateFailAndCount(Request $Request, Validator $Validator, int $Count)
	{
		
		// if validation or number of json parameters fails send status response and stop
		if ( $Validator->fails() || count($Request->all()) !== $Count )
		{
			
			response()->json([
				'status' => '400', 'message' => 'Неверные параметры запроса (тип или количество параметров)'
			], 400)->send();
			exit;
			
		}
		
	}
	
	/**
	 * Creates and returns Redis instance
	 * 
	 * @return $redis Redis Instance
	 */
	public static function CreateRedisInstance()
	{
		
		// connect with redis server and check if server is available
		try
		{
			
			//server and port
			$redisServerAddress = config('database.redis.default.host');
			$redisServerPort = config('database.redis.default.port');
			
			//create redis instance
			$redis = new Redis();
			
			//connect with server and port
			$redis->connect( $redisServerAddress, $redisServerPort );
			
		}
		// show info if exception-error and exit
		catch (Exception $ex)
		{
			
			echo $ex->getMessage();
			echo "Redis server $redisServerAddress:$redisServerPort not available ...";
			exit;
			
		}
		
		return $redis;
		
	}
	
}