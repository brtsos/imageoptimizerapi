<?php

namespace App\Controllers;

use App\Models\User;
use App\Models\History;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class ImageController extends Controller
{
	public function postCompressImage($request, $response)
	{
		$return = [];
		
		$uri = $request->getUri();

		$file = $request->getUploadedFiles();

		if (empty($file)) {
            $return['message'] = "Image don't found.";
            return $response->withJson($return, 400);
        } else {
		    if (!isset($file['image'])) {
                $return['message'] = "Image don't found.";
                return $response->withJson($return, 400);
            }
        }

        $user = User::where('name', explode(':', $uri->getUserInfo()))->first();

        if (is_null($user)) {
            $return['message'] = 'User do not found.';
            return $response->withJson($return, 401);
        }

        if ($user->activ != 1) {
            $return['message'] = 'Account email is not confirmed.';
            return $response->withJson($return, 401);
        }

        $imageType = $file['image']->getClientMediaType();

        if ($imageType != 'image/jpeg' && $imageType != 'image/jpg' && $imageType != 'image/png') {
			$return['message'] = 'Bad image format.';
            return $response->withJson($return, 400);
		}

        if (strlen($file['image']->getClientFilename()) > 250) {
            $return['message'] = 'File name is too long. Max 250 chars.';
            return $response->withJson($return, 400);
        }

        //@todo image is too big
        //if ($file['image']->getSize > getenv('MAX_FILE_UPLOAD_SIZE')) {
        //    $return['message'] = 'Image is too big.';
        //    return $response->withJson($return, 400);
        //}

        $folder = hash('sha256', '73ac459a2bbb629e59c7c90564eae39f' . date('sYhmdi'));
		mkdir("tmp/" . $folder, 0777);
		
		if (!is_null($file['image']->moveTo('tmp/' . $folder . '/' . $file['image']->getClientFilename()))) {
			$return['message'] = 'Convert error. Please conntact with administrator.';
			return $response->withJson($return, 400);
		}
		
        // check user limit
		$requestCount = History::where('userid', $user->id)
            ->whereYear('created_at', '=', date('Y'))
            ->whereMonth('created_at', '=', date('m'))
            ->count();

        if ($requestCount > $user->request_limit) {
            $return['message'] = 'Too many requests. Monthly limit exceeded.';
            return $response->withJson($return, 400);
        }

		//@todo check if user payed
		
        // convert image
        $log = new Logger('logs');
        $log->pushHandler(new StreamHandler('/var/www/default/imageoptimizerapi/public/log.log',
            Logger::ERROR));

        $factory = new \ImageOptimizer\OptimizerFactory(
            array(
                'ignore_errors' => false,
                'pngquant_bin' => '/usr/bin/pngquant',
                'optipng_bin' => '/usr/bin/optipng',
                'jpegoptim_bin' => '/usr/bin/jpegoptim'
            ),
            $log
        );

        $optimizer = $factory->get();
        $filepath = '/var/www/default/imageoptimizerapi/public/tmp/' . $folder . '/' . $file['image']->getClientFilename();
		$optimizer->optimize($filepath);

		$return['message'] = 'Image compresed successful.';
		$return['path_to_file'] = $this->container->settings['baseUrl'] . '/tmp/' . $folder . '/' .
                                  $file['image']->getClientFilename();

        History::create([
            'userid' => $user->id,
            'filename' => $file['image']->getClientFilename(),
            'directory' => $folder
        ]);

		return $response->withJson($return, 200, JSON_UNESCAPED_SLASHES);
	}

    public function getCompressImage($request, $response)
    {
        $return = [];
        $return['message'] = 'You should use POST.';
        return $response->withJson($return, 202);
    }

}
