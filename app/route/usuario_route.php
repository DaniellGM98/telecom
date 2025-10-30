<?php
	use App\Lib\Response,
		App\Lib\MiddlewareToken,
		PHPMailer\PHPMailer\PHPMailer,
		PHPMailer\PHPMailer\Exception,
		Slim\Http\UploadedFile;

	$app->group('/usuario/', function() use ($app) {
		$this->get('', function($request, $response, $arguments) {
			return $response->withHeader('Content-type', 'text/html')->write('Soy ruta de usuario');
		});

		$this->get('getByMD5/{value}/{campo}', function($request, $response, $arguments) {
			return $response->withJson($this->model->usuario->getByMD5($arguments['value'], $arguments['campo']));
		});

		$this->get('get/{id}', function($request, $response, $arguments) {
			return $response->withJson($this->model->usuario->get($arguments['id']));
		})->add( new MiddlewareToken() );

		$this->get('find/{busqueda}[/{usuario_tipo_id}]', function($request, $response, $arguments) {
			$arguments['usuario_tipo_id'] = isset($arguments['usuario_tipo_id'])? $arguments['usuario_tipo_id']: 0;
			return $response->withJson($this->model->usuario->find($arguments['busqueda'], $arguments['usuario_tipo_id']));
		})->add( new MiddlewareToken() );

		$this->get('getByEmail/{email}', function($request, $response, $arguments) {
			return $response->withJson($this->model->usuario->getByEmail($arguments['email']));
		})->add( new MiddlewareToken() );

		$this->get('getAll/{pagina}/{limite}[/{usuario_tipo_id}[/{busqueda}]]', function($request, $response, $arguments) {
			$arguments['usuario_tipo_id'] = isset($arguments['usuario_tipo_id'])? $arguments['usuario_tipo_id']: 0;
			$arguments['busqueda'] = isset($arguments['busqueda'])? $arguments['busqueda']: 0;
			return $response->withJson($this->model->usuario->getAll($arguments['pagina'], $arguments['limite']), $arguments['usuario_tipo_id'], $arguments['busqueda']);
		})->add( new MiddlewareToken() );

		$this->get('getAllbusca/{pagina}/{limite}/{tipo_usuario_id}/{busqueda}', function($request, $response, $arguments) {
			return $response->withJson($this->model->usuario->getAllbusca($arguments['pagina'], $arguments['limite'], $arguments['tipo_usuario_id'], $arguments['busqueda']));
		})->add( new MiddlewareToken() );

		$this->get('getByCodigoVerificacion/{codigo}', function($request, $response, $arguments) {
			return $response->withJson($this->model->usuario->getByCodigoVerificacion($arguments['codigo']));
		});

		$this->get('getPermisos/{id}', function($request, $response, $arguments) {
			return $response->withJson($this->model->usuario->getPermisos($arguments['id']));
		})->add( new MiddlewareToken() );

		$this->put('edit/{id}', function($request, $response, $arguments) {
			require_once './core/defines.php';
			$this->response = new Response();
			$this->response->state = $this->model->transaction->iniciaTransaccion();
			$parsedBody = $request->getParsedBody();
			if(isset($parsedBody['sucursal_id'])) { $sucursal_id = $parsedBody['sucursal_id']; unset($parsedBody['sucursal_id']); } else { $sucursal_id = 0; }
			if(isset($parsedBody['contrasena']) && strlen($parsedBody['contrasena'])>0) { $parsedBody['contrasena']=md5(sha1($parsedBody['contrasena'])); }
			elseif(isset($parsedBody['contrasena'])) { unset($parsedBody['contrasena']); }
			$id_usuario = $arguments['id']; $orgInfo = $this->model->usuario->get($id_usuario)->result;
			$areTheSame = true; foreach($parsedBody as $field => $value) {
				if($orgInfo->$field != $value) {
					$areTheSame = false; break;
				}
			}

			$usuario = $this->model->usuario->edit($parsedBody, $arguments['id']);
			if($usuario->response || $areTheSame) { $this->response->areTheSame = $areTheSame;
				if(!$this->response->areTheSame) {
					$seg_log = $this->model->seg_log->add('Actualización información usuario', 'usuario', $arguments['id']);
					if($seg_log->response) {
						$this->response->result = $usuario->result;
						$this->response->state = $this->model->transaction->confirmaTransaccion();
						$this->response->SetResponse(true);
					} else {
						$this->response->result = $seg_log->result;
						$this->response->errors = $seg_log->errors;
						$this->response->state = $this->model->transaction->regresaTransaccion();
						$this->response->SetResponse(false, $seg_log->message);
					}
				}

				$this->response->SetResponse(true);
			} else {
				$this->response->result = $usuario->result;
				$this->response->errors = $usuario->errors;
				$this->response->state = $this->model->transaction->regresaTransaccion();
				$this->response->SetResponse(false, $usuario->message);
			}

			return $response->withJson($this->response);
		})->add( new MiddlewareToken() );
		
		$this->post('login/', function($request, $response, $arguments) {
			if(!isset($_SESSION)) { session_start(); }
			$this->response = new Response();
			$this->response->state = $this->model->transaction->iniciaTransaccion();
			$parsedBody= $request->getParsedBody();
			$email= $parsedBody['email'];
			$contrasena = $parsedBody['contrasena'];

			$usuario = $this->model->usuario->login($email, $contrasena);
			if($usuario->response) {
				$token = $this->model->seg_sesion->crearToken($usuario->result);
				$data = [
					'usuario_id' => $usuario->result->id,
					'ip_address' => $_SERVER['REMOTE_ADDR'],
					'user_agent' => $_SERVER['HTTP_USER_AGENT'],
					'inicio' => date('Y-m-d H:i:s'),
					'token' => $token,
				];
				$sesion = $this->model->seg_sesion->add($data);
				if($sesion->response) {
					$seg_log = $this->model->seg_log->add('Inicio de sesión', 'seg_sesion', $_SESSION['id_sesion']);
					$_SESSION['sucursal'] = null;
					if($seg_log->response) {
						$this->response->result = $usuario;
						$this->response->state = $this->model->transaction->confirmaTransaccion();
						$this->response->SetResponse(true);
					} else {
						$this->response->result = $seg_log->result;
						$this->response->errors = $seg_log->errors;
						$this->response->state = $this->model->transaction->regresaTransaccion();
						$this->response->SetResponse(false, $seg_log->message);
					}
				} else {
					$this->response->result = $sesion->result;
					$this->response->errors = $sesion->errors;
					$this->response->state = $this->model->transaction->regresaTransaccion();
					$this->response->SetResponse(false, $sesion->message);
				}
			}

			return $response->withJson($this->response);
		});

		$this->put('changePassword/{id}', function($request, $response, $arguments) {
			require_once './core/defines.php';
			$this->response = new Response();
			$this->response->state = $this->model->transaction->iniciaTransaccion();

			$resultado = $this->model->usuario->changePassword($request->getParsedBody(), $arguments['id']);
			if($resultado->response) {
				$seg_log = $this->model->seg_log->add('Cambiar Contraseña', 'usuario', $arguments['id']);
				if($seg_log->response) {
					$this->response->result = $resultado->result;
					$this->response->state = $this->model->transaction->confirmaTransaccion();
					$this->response->SetResponse(true);
				} else {
					$this->response->result = $seg_log->result;
					$this->response->errors = $seg_log->errors;
					$this->response->state = $this->model->transaction->regresaTransaccion();
					$this->response->SetResponse(false, $seg_log->message);
				}
			} else {
				$this->response->result = $resultado->result;
				$this->response->errors = $resultado->errors;
				$this->response->state = $this->model->transaction->regresaTransaccion();
				$this->response->SetResponse(false, $resultado->message);
			}

			return $response->withJson($this->response);
		})->add( new MiddlewareToken() );

		$this->put('changePassword/', function($request, $response, $arguments) {
			require_once './core/defines.php';
			$this->response = new Response();
			$this->response->state = $this->model->transaction->iniciaTransaccion();

			$resultado = $this->model->usuario->changePassword($request->getParsedBody(), $_SESSION['usuario']->id);
			if($resultado->response) {
				$seg_log = $this->model->seg_log->add('Cambiar Contraseña', 'usuario', $_SESSION['usuario']->id);
				if($seg_log->response) {
					$this->response->result = $resultado->result;
					$this->response->state = $this->model->transaction->confirmaTransaccion();
					$this->response->SetResponse(true);
				} else {
					$this->response->result = $seg_log->result;
					$this->response->errors = $seg_log->errors;
					$this->response->state = $this->model->transaction->regresaTransaccion();
					$this->response->SetResponse(false, $seg_log->message);
				}
			} else {
				$this->response->result = $resultado->result;
				$this->response->errors = $resultado->errors;
				$this->response->state = $this->model->transaction->regresaTransaccion();
				$this->response->SetResponse(false, $resultado->message);
			}

			return $response->withJson($this->response);
		})->add( new MiddlewareToken() );

		/** 
		 * setPushId/USER_ID 
		 * asigna el token de FireBase para el usuario
		*/
		$this->put('setPushId/{id}', function($request, $response, $arguments) {
			return $response->withjson($this->model->usuario->edit($arguments['id'], $request->getParsedBody()));
		});

		$this->post('recoveryPass', function($request, $response, $arguments) {
			$this->response = new Response();
			$parsedBody = $request->getParsedBody();

			$cadena = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz1234567890@#!&()";
			$longitudCadena=strlen($cadena);
			$pass = "";
			$longitudPass = 8;
			for($i=1 ; $i<=$longitudPass ; $i++) {
				$pos = rand(0, $longitudCadena-1);
				$pass .= substr($cadena, $pos, 1);
			}

			$correo = $parsedBody['email'];
			$parsedBody['new_password'] = $pass;

			$mail = new PHPMailer(true);                              // Passing `true` enables exceptions
			try {
				//Server settings
				$mail->SMTPDebug = 0;                                 // Enable verbose debug output
				$mail->isSMTP();                                      // Set mailer to use SMTP
				$mail->Host = 'smtp.gmail.com';  // Specify main and backup SMTP servers
				$mail->SMTPOptions = array(
					'ssl'=> array(
						'verify_peer' => false,
						'verify_peer_name'=> false,
						'allow_self_signed' => true
					)
				);
				$mail->SMTPAuth = true;                               // Enable SMTP authentication
				$mail->Username = $_SESSION['mail_username'];                 // SMTP username
				$mail->Password = $_SESSION['mail_pwd'];                           // SMTP password
				$mail->SMTPSecure = 'tls';                            // Enable TLS encryption, `ssl` also accepted
				$mail->Port = 587;                                    // TCP port to connect to

				//Recipients
				$mail->setFrom($_SESSION['mail_username'], SITE_NAME);
				$mail->addAddress($correo);    // Add a recipient
				//$mail->addAddress($parsedBody['email']);               // Name is optional
				//$mail->addReplyTo('mita@dds.media', 'Information');
				//$mail->addCC('joselyn@ddsmedia.net');
		
				//Content
				$mail->isHTML(true);                                  // Set email format to HTML
				$mail->Subject = 'Reestablecimiento de contraseña';
				$mail->Body    = 'Tu nueva Contraseña es: '. $pass;
				$mail->AltBody = 'Tu nueva Contrasena es: '. $pass;

				$var = $this->model->usuario->recoveryPass($parsedBody);

				if ($var->result == 1) {
					$mail->send();
					$this->response->result = '1';
					return $response->withjson($this->response->SetResponse(true, 'Correo enviado correctamente'));
				}else {
					$this->response->result = '0';
					return $response->withjson($this->response->SetResponse(false, 'Contraseña no actualizada, correo no valido'));
				}
			}
			catch (Exception $e) {
				return $response->withjson($this->response->SetResponse(false, $e->getMessage()));
			}
		});

		$this->get('logout/', function($request, $response, $arguments) use ($app) {
			if(!isset($_SESSION)) { session_start(); }
			if(isset($_SESSION['sucursal'])) { $this->model->sucursal->liberarSucursal($_SESSION['sucursal']); }
			$resultado = $this->model->seg_sesion->logout();

			return $this->response->withRedirect('../../login');
		});

		$this->post('uploadImagenUsuario', function($request, $response, $arguments) {
			$this->response = new Response();
			$parsedBody = $request->getParsedBody();

			$directory = 'data/foto/';
			$uploadedFiles = $request->getUploadedFiles();
			$uploadedFile = $uploadedFiles['imagen'];
			if ($uploadedFile->getError() === UPLOAD_ERR_OK) {
				//session_start();
				$fileName = md5($_SESSION['usuario']->id).'.jpg';
				$filename = $this->model->usuario->moveUploadedFile($directory, $uploadedFile, $fileName);
				if($filename == '0') {
					$this->response->result = 0;
					return $this->response->SetResponse(false, 'Extensión de archivo invalido, solo se aceptan imagenes en formato jpg');
				} else {
					$_SESSION['usuario']->foto = true;
					$this->response->result = 1;
					$this->response->filename = $filename.'?'.rand();
					$this->response->SetResponse(true,'Archivo cargado con exito: ' . $filename);
					return $response->withjson($this->response);
				}
			}

			$this->response->result = 1;
			return $this->response->SetResponse(true,'Archivo cargado con exito: ' . $filename);
		})->add( new MiddlewareToken() );

		/**
		 * Método editProfile
		 * Actualiza los datos del usuario logeado
		 * by isantosp
		 */
		$this->put('editProfile/', function($request, $response, $arguments) {
			require_once './core/defines.php';
			$this->response = new Response();
			$this->response->state = $this->model->transaction->iniciaTransaccion();
			$parsedBody = $request->getParsedBody();
			$usuario = $this->model->usuario->edit($parsedBody, $_SESSION['usuario']->id);
			if($usuario->result == 1) {
				$_SESSION['usuario']->nombre = $parsedBody['nombre'];
				$_SESSION['usuario']->apellidos = $parsedBody['apellidos'];
				$_SESSION['usuario']->telefono = $parsedBody['telefono'];
				$usuario->nombre = $parsedBody['nombre'].' '.$parsedBody['apellidos'];

				$seg_log = $this->model->seg_log->add('Actualizar información usuario', 'usuario', $_SESSION['usuario']->id);
				if($seg_log->response) {
					$this->response->result = $usuario->result;
					$this->response->state = $this->model->transaction->confirmaTransaccion();
					$this->response->SetResponse(true);
				} else {
					$this->response->result = $seg_log->result;
					$this->response->errors = $seg_log->errors;
					$this->response->state = $this->model->transaction->regresaTransaccion();
					$this->response->SetResponse(false, $seg_log->message);
				}
			} else {
				$this->response->result = $usuario->result;
				$this->response->errors = $usuario->errors;
				$this->response->state = $this->model->transaction->regresaTransaccion();
				$this->response->SetResponse(false, $usuario->message);
			}

			return $response->withJson($this->response);
		})->add( new MiddlewareToken() );

		$this->post('renovarToken/', function($request, $response, $arguments) {
			require_once './core/defines.php';
			$data = [
				'token' => $this->model->seg_sesion->crearToken($_SESSION['usuario']),
				'fin' => date('Y-m-d H:i:s'),
			];
			
			return $response->withJson($this->model->seg_sesion->edit($data, $_SESSION['id_sesion']));
		})->add( new MiddlewareToken() );

		$this->put('del/{id}', function($request, $response, $arguments) {
			require_once './core/defines.php';
			$this->response = new Response();
			$this->response->state = $this->model->transaction->iniciaTransaccion();
			$parsedBody = $request->getParsedBody();

			$usuario = $this->model->usuario->del($arguments['id']);
			if($usuario->response) {
				$seg_log = $this->model->seg_log->add('Usuario dado de baja', 'usuario', $arguments['id']);
				if($seg_log->response) {
					$this->response->result = $usuario->result;
					$this->response->state = $this->model->transaction->confirmaTransaccion();
					$this->response->SetResponse(true);
				} else {
					$this->response->result = $seg_log->result;
					$this->response->errors = $seg_log->errors;
					$this->response->state = $this->model->transaction->regresaTransaccion();
					$this->response->SetResponse(false, $seg_log->message);
				}
			} else {
				$this->response->result = $usuario->result;
				$this->response->errors = $usuario->errors;
				$this->response->state = $this->model->transaction->regresaTransaccion();
				$this->response->SetResponse(false, $usuario->message);
			}
			
			return $response->withJson($this->response);
		})->add( new MiddlewareToken() );



		// SUELDOS
		$this->get('getSueldos', function($request, $response, $arguments) {
			$users = $this->model->usuario->getVendedores()->result;

			foreach ($users as $user) {
				$user->pago = $this->model->usuario->getPago($user->id);
			}

			return $response->withJson($users);
		})->add( new MiddlewareToken() );

		$this->post('editPago', function($request, $response, $arguments) {
            $parsedBody = $request->getParsedBody();
            $user = $parsedBody['user'];
			unset($parsedBody['user']);
			//$data = array($parsedBody['name'] => $parsedBody['value']);
            $resultado = $this->model->usuario->editPago($parsedBody, $user);
			if($resultado)
                $this->model->seg_log->add('Modifica Sueldo Pago '.json_encode($parsedBody), 'pago', $user);
            return $response->withJson($resultado);
		})->add( new MiddlewareToken() );
	});

	function getCodigoAleatorio($longitud) {
		$universo = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz1234567890";
		$longUniverso = strlen($universo);
		$codigo = "";
		while(strlen($codigo) < $longitud) {
			$codigo .= substr($universo, rand(0, $longUniverso-1), 1);
		}
		
		return $codigo;
	}
?>