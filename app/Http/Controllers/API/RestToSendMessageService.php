<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use App\Http\Middleware\OpenApiRequestsHelpers;

class RestToSendMessageService extends Controller
{
	
	// default maintenance mode value
	private bool $MaintenanceMode = false;
	
	// container for redis instance
	private $redis;
	
	// container for redis calls cache name
	private string $SendMessageRedisCallsCache = '';
	
	// container for SendMessage Api URL
	private string $SendMessageServiceApiUrl ='';
	
	
	/**
	 * Constructor, main flow and entry point for class
	 *
	 * @param Illuminate\Http\Request $Request
	 */
	function __construct(Request $Request)
	{
		
		// for test purpose - actual maintenance variable can get bool parameter from config or database
		$this->MaintenanceMode = false;
		
		OpenApiRequestsHelpers::ValidateRequestByType($Request, 'POST', 'application/json');
		
		OpenApiRequestsHelpers::MaintenanceModeCheck($this->MaintenanceMode);
		
		// validate fields of incoming request for mass-sending sms
		$Validator = Validator::make($Request->all(), [
			'to' => 'required|array',
			'to.*' => 'required|integer',
			'message' => 'required|string|max:160',
		]);
		OpenApiRequestsHelpers::SubValidateFailAndCount($Request, $Validator, 2);
		
		// send response to SendMessageService api and cache to redis if fails
		$this->redis = OpenApiRequestsHelpers::CreateRedisInstance();
		$this->SendMessageRedisCallsCache = 'SendMessageServiceApiCache';
		$this->SendMessageServiceApiUrl = 'http://project.com/employmentTest/public/api/SendMessageService';
		
		// send incoming request
		$RequestArray = $Request->all();
		foreach ( $RequestArray['to'] as $key => $value )
		{
			
			// send POST request
			$response = Http::post($this->SendMessageServiceApiUrl, [
				'to' => $value,
				'message' => $RequestArray['message']
			]);
			
			// cache to redis unique set if sms not sended
			if ( $response->getStatusCode() !== 200 )
			{
				
				$this->redis->sAdd($this->SendMessageRedisCallsCache,  json_encode([
					'to' => $value,
					'message' => $RequestArray['message']
				]));
				
			}
			
		}
		
		// if latest response fails show status and stop after sending info response
		if ( $response->getStatusCode() !== 200 )
		{
			
			response()->json([
				'status' => '500',
				'message' => 'Сервис временно недоступен (произошла ошибка), Ваши SMS на указанные номера сохранены и будут отправлены после восстановления сервиса, отправка идентичных SMS на указанные номера производится не будет. Вы можете отправлять SMS с другим текстом на эти же и другие номера, они будут установлены в очередь на отправку.'
			], 500)->send();
			exit;
			
		}
		
		// check and send api cache values if previous sending succeed
		$this->CheckRedisSendMessageApiCache();
		
		// if success, send service json success response
		response()->json(['status' => '200', 'message' => 'Сообщение успешно обработано'], 200)->send();
		exit;
		
	}
	
	
	/**
	 * Check for cached unique values in redis set and send request
	 * if request succeed then remove value from set to free memory
	 *
	 * @return void
	 */
	private function CheckRedisSendMessageApiCache()
	{
		
		$SavedApiCalls = $this->redis->sMembers($this->SendMessageRedisCallsCache);
		if ( ! empty( $SavedApiCalls ) )
		{
			
			foreach ( $SavedApiCalls as $key => $JsonValue )
			{
				
				$DecodedValue = json_decode($JsonValue, true);
				
				$response = Http::post($this->SendMessageServiceApiUrl, [
					'to' => $DecodedValue['to'],
					'message' => $DecodedValue['message']
				]);
				
				// delete from redis unique set if sms sended
				if ( $response->getStatusCode() == 200 )
				{
					// var_dump($response->getStatusCode());
					$this->redis->sRem($this->SendMessageRedisCallsCache, $JsonValue);
					
				}
				
			}
			
		}
		
	}
	
	
}