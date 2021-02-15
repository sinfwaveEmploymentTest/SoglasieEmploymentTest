<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use App\Http\Middleware\OpenApiRequestsHelpers;

class SendMessageService extends Controller
{
	
	// default maintenance mode value
	private bool $MaintenanceMode = false;
	
	function __construct(Request $Request)
	{
		
		// for test purpose - actual maintenance variable can get bool parameter from config or database
		$this->MaintenanceMode = false;
		
		OpenApiRequestsHelpers::ValidateRequestByType($Request, 'POST', 'application/json');
		
		OpenApiRequestsHelpers::MaintenanceModeCheck($this->MaintenanceMode);
		
		// validate fields of incoming request for sending sms
		$Validator = Validator::make($Request->all(), [
			'to' => 'required|integer',
			'message' => 'required|string|max:160',
		]);
		OpenApiRequestsHelpers::SubValidateFailAndCount($Request, $Validator, 2);
		
		// "send sms" to log
		$RequestArray = $Request->all();
		Log::channel('sendmessageservice')->info(
			"to: ".$RequestArray['to']." message: ".$RequestArray['message']." - СМС отправлено."
		);
		
		// if success send service json success response and stop
		response()->json(['status' => '200', 'message' => 'Сообщение успешно обработано'], 200)->send();
		exit;
		
	}
	
}