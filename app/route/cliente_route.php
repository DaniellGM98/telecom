<?php
	use App\Lib\Response,
		App\Lib\MiddlewareToken;

	/*** Grupo bajo la ruta cliente ***/  
	$app->group('/cliente/', function () {
		$this->get('', function($request, $response, $arguments) {
			return $res->withHeader('Content-type', 'text/html')->write('Soy ruta de cliente');
		})->add( new MiddlewareToken() );

		$this->get('get/{id}', function($request, $response, $arguments) {
			$resultado = $response->withJson($this->model->cliente->get($arguments['id']));
			$resultado->timbres = $this->model->timbres->getDisponibles()->result;

			return $resultado;
		})->add( new MiddlewareToken() );

		$this->get('find/{filtro}', function($request, $response, $arguments) {
			return $response->withJson($this->model->cliente->find($arguments['filtro']));
		})->add( new MiddlewareToken() );

		$this->get('getAll/[{pagina}/{limite}]', function($request, $response, $arguments) {
			$arguments['pagina'] = isset($arguments['pagina'])? $arguments['pagina']: 0;
			$arguments['limite'] = isset($arguments['limite'])? $arguments['limite']: 0;
			return $response->withJson($this->model->cliente->getAll($arguments['pagina'], $arguments['limite']));
		})->add( new MiddlewareToken() );

		$this->get('getAllBusca/[{pagina}/{limite}/{filtro}]', function($request, $response, $arguments) {
			$arguments['pagina'] = isset($arguments['pagina'])? $arguments['pagina']: 0;
			$arguments['limite'] = isset($arguments['limite'])? $arguments['limite']: 0;
			$arguments['busqueda'] = isset($arguments['busqueda'])? $arguments['busqueda']: 0;
			return $response->withJson($this->model->cliente->getAll($arguments['pagina'], $arguments['limite'], $arguments['busqueda']));
		})->add( new MiddlewareToken() );

		$this->post('add/', function($request, $response, $arguments) {
			if(!isset($_SESSION)) { session_start(); }
			$this->response = new Response();
			$this->response->state = $this->model->transaction->iniciaTransaccion();
			
			$parsedBody = $request->getParsedBody();
			// foreach($parsedBody as $key => $value) {
			// 	$parsedBody[$key] = utf8_encode($value);
			// }

			if(strlen($parsedBody['correo'])==0 || !$this->model->usuario->getByEmail($parsedBody['correo'])->response) {
				$parsedBody['codigo'] = intval($parsedBody['status'])==2? getCodigoAleatorio(8): '';
				$password = isset($parsedBody['contrasena'])? $parsedBody['contrasena']: getCodigoAleatorio(8);
				$data = [
					'nombre' => $parsedBody['nombre'],
					'apellidos' => $parsedBody['apellidos'],
					'telefono' => $parsedBody['telefono'],
					'email' => $parsedBody['correo'],
					'contrasena' => md5(sha1($password)),
					'usuario_tipo_id' => 3,
					'codigo' => $parsedBody['codigo'],
					//'status' => $parsedBody['status'],
				];
				$usuario = $this->model->usuario->add($data);
				if($usuario->response) {
					$data = [
						'usuario_id' => $usuario->result,
						'rfc' => $parsedBody['rfc'],
						'razon_social' => $parsedBody['razon_social'],
						'uso_cfdi' => $parsedBody['uso_cfdi'],
						'descuento' => (isset($parsedBody['descuento']) && is_int($parsedBody['descuento']))? $parsedBody['descuento']: 0,
					];
					if(isset($parsedBody['direccion'])) { $data['direccion'] = $parsedBody['direccion']; }
					if(isset($parsedBody['tiene_credito'])) { $data['tiene_credito'] = $parsedBody['tiene_credito']; }
					if(isset($parsedBody['credito_dias'])) { $data['credito_dias'] = $parsedBody['credito_dias']; }
					$cliente = $this->model->cliente->add($data);
					if($cliente->response) {
						if(strlen($parsedBody['correo']) > 0) {
							/*if(intval($parsedBody['status']) == 2) {
								$subject = 'Confirma tu correo electrónico';
								$body = "
									<table class='mobile-width' width='550' bgcolor='#ffffff' align='center' cellspacing='0' cellpadding='0' border='0'>
										<tbody>
											<tr>
												<td align='center'>
													<table width='100%' cellspacing='0' cellpadding='0' border='0' class='full-width' >
														<tbody>
															<tr>
																<td height='40'>&nbsp;</td>
															</tr>
															<tr>
																<td class='front' style='font-family: Open Sans, sans-serif; font-size: 30px; mso-line-height-rule:exactly; line-height:48px; font-weight:normal; color: #000000;' align='center'>CONFIRMA TU CUENTA DE CORREO</td>
															</tr>
															<tr>
																<td height='10'>&nbsp;</td>
															</tr>
														</tbody>
													</table>
												</td>
											</tr>
										</tbody>
									</table>
									<table width='600' bgcolor='#ecf0f1' align='center' cellspacing='0' cellpadding='0' border='0' class='mobile-width'>
										<tbody>
											<tr>
												<td align='center'>
													<table width='550' align='center' cellspacing='0' cellpadding='0' border='0' class='content-width'>
														<tbody>
															<tr>
																<td align='center'>
																	<table width='100%' cellspacing='0' cellpadding='0' border='0' class='full-width' >
																		<tbody>
																			<tr>
																				<td height='52'>&nbsp;</td>
																			</tr>
																		</tbody>
																	</table>
																	<table width='100%' align='left' cellspacing='0' cellpadding='0' border='0' class='full-width'>
																		<tbody>
																			<tr>
																				<td style='font-size:15px; color:#2c3e50; font-weight:bold; font-family: Open Sans, sans-serif; text-align:left' mc:edit='section4_title1'>Hola, ".mb_strtoupper(utf8_decode($parsedBody['nombre']))."!</td>
																			</tr>
																			<tr >
																				<td style='font-size:13px; line-height:16px; color:#95a5a6; font-weight:normal; font-family: Open Sans, sans-serif;' align='left' mc:edit='section4_text1'>Gracias por registrar una cuenta en Tienda Casa Perez</td>
																			</tr>
																			<tr>
																				<td height='5' style='font-size:10px; line-height:10px;'>&nbsp;</td>
																			</tr>
																			<tr>
																				<td style='font-size:13px; line-height:16px; color:#95a5a6; font-weight:normal; font-family: Open Sans, sans-serif;' align='left' mc:edit='section4_text6'>Estas a un solo paso de empezar a disfrutar de la tienda en línea. Ahora s&oacute;lo necesitas dar click en el enlace con el texto 'VALIDAR MI CORREO ELECTR&Oacute;NICO'.
																				</td>
																			</tr>																					
																			<tr>
																				<td height='5' style='font-size:10px; line-height:10px;'>&nbsp;</td>
																			</tr>
																			<tr>
																				<td style='font-size:13px; line-height:16px; color:#95a5a6; font-weight:normal; font-family: Open Sans, sans-serif;' align='left' mc:edit='section4_text6'>Posteriormente, para poder acceder al sistema, se ha generado una contraseña aleatoria, la cual es: '$password'. Se recomienda que lo primero que hagas sea actualizarla.
																				</td>
																			</tr>																					
																			<tr>
																				<td height='10' style='font-size:30px; line-height:10px;'>&nbsp;</td>
																			</tr>																					
																			<tr>
																				<td height='10' style='font-size:30px; line-height:10px;'>&nbsp;</td>
																			</tr>																					
																			<tr>
																				<td align='center'>
																					<table align='left' cellspacing='0' cellpadding='0' border='0'>
																						<tbody>
																							<tr>
																								<td align='center' style='border:#2980b9 solid 0px; border-radius:4px; color:#2980b9; display:block; font-family: Open Sans, sans-serif; font-size:12px; font-weight:bold; line-height:12px; text-align:center; text-decoration:none; padding-top: 10px; padding-bottom: 10px; -webkit-text-size-adjust:none;' mc:edit='section4_button1'><a style='color:#2980b9;font-family: Open Sans, sans-serif; font-size:13px; font-weight:800;' href='".URL_ROOT."/confirm-email/$parsedBody[codigo]'>VALIDAR MI CORREO ELECTR&Oacute;NICO</a></td>
																							</tr>
																						</tbody>
																					</table>
																				</td>
																			</tr>
																			<tr>
																				<td height='20' style='font-size:30px; line-height:20px;'>&nbsp;</td>
																			</tr>																					
																		</tbody>
																	</table>	
																</td>
															</tr>
														</tbody>
													</table>
												</td>
											</tr>
										</tbody>
									</table>
								";
								$this->response->email = $this->model->usuario->sendEmail($parsedBody['correo'], $subject, $body);
							}
	
							$subject = 'Se ha registrado un nuevo cliente';
							$body = "
								<table class='mobile-width' width='550' bgcolor='#ffffff' align='center' cellspacing='0' cellpadding='0' border='0'>
									<tbody>
										<tr>
											<td align='center'>
												<table width='100%' cellspacing='0' cellpadding='0' border='0' class='full-width' >
													<tbody>
														<tr>
															<td height='40'>&nbsp;</td>
														</tr>
														<tr>
															<td class='front' style='font-family: Open Sans, sans-serif; font-size: 30px; mso-line-height-rule:exactly; line-height:48px; font-weight:normal; color: #000000;' align='center'>SE HA REGISTRADO UN NUEVO CLIENTE EN LA TIENDA</td>
														</tr>
														<tr>
															<td height='10'>&nbsp;</td>
														</tr>
													</tbody>
												</table>
											</td>
										</tr>
									</tbody>
								</table>
							";
							$this->response->email = $this->model->usuario->sendEmail($_SESSION['mail_username'], $subject, $body);*/
						}

						$seg_log = $this->model->seg_log->add('Registro nuevo cliente', 'cliente', $usuario->result);
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
						$this->response->result = $cliente->result;
						$this->response->errors = $cliente->errors;
						$this->response->state = $this->model->transaction->regresaTransaccion();
						$this->response->SetResponse(false, $cliente->message);
					}
				} else {
					$this->response->result = $usuario->result;
					$this->response->errors = $usuario->errors;
					$this->response->state = $this->model->transaction->regresaTransaccion();
					$this->response->SetResponse(false, $usuario->message);
				}
			} else {
				$this->response->state = $this->model->transaction->regresaTransaccion();
				$this->response->SetResponse(false, 'El correo ingresado ya existe. Por favor, intente con otro.');
			}

			return $response->withJson($this->response);
		});

		$this->put('edit/{id}', function($request, $response, $arguments) {
			if(!isset($_SESSION)) { session_start(); }
			$this->response = new Response();
			$this->response->state = $this->model->transaction->iniciaTransaccion();
			
			$parsedBody = $request->getParsedBody();
			// foreach($parsedBody as $key => $value) {
			// 	$parsedBody[$key] = utf8_encode($value);
			// }

				$data = [
					'nombre' => $parsedBody['nombre'],
					'apellidos' => $parsedBody['apellidos'],
					'telefono' => $parsedBody['telefono'],
				];
				if(isset($parsedBody['codigo'])) { $data['codigo'] = $parsedBody['codigo']; }
				if(isset($parsedBody['status'])) { $data['status'] = $parsedBody['status']; }
				if(isset($parsedBody['contrasena']) && strlen($parsedBody['contrasena'])>0) { $parsedBody['contrasena'] = md5(sha1($parsedBody['contrasena'])); }
				if(isset($parsedBody['contrasena'])) { unset($parsedBody['contrasena']); }
				
				$infoUsuario = $this->model->usuario->getByEmail($parsedBody['correo'])->result;
				$areTheSame = true;
				foreach($data as $name => $value) {
					if($infoUsuario->$name != $value) {
						$areTheSame = false;
						break;
					}
				}

				$usuario = $this->model->usuario->edit($data, $arguments['id']);
				if($usuario->response || $areTheSame) {
					$data = [
						'rfc' => $parsedBody['rfc'],
						'razon_social' => $parsedBody['razon_social'],
						'uso_cfdi' => $parsedBody['uso_cfdi'],
						// 'es_taller' => $parsedBody['es_taller'],
						// 'tiene_credito' => $parsedBody['tiene_credito'],
						// 'credito_dias' => $parsedBody['credito_dias'],
					];
					if(isset($parsedBody['direccion'])) { $data['direccion'] = $parsedBody['direccion']; }
					if(isset($parsedBody['es_taller'])) { $data['es_taller'] = $parsedBody['es_taller']; }
					if(isset($parsedBody['tiene_credito'])) { $data['tiene_credito'] = $parsedBody['tiene_credito']; }
					if(isset($parsedBody['credito_dias'])) { $data['credito_dias'] = $parsedBody['credito_dias']; }
					$cliente = $this->model->cliente->edit($data, $arguments['id']);
					if($cliente->response) {
						$seg_log = $this->model->seg_log->add('Actualización información cliente', 'cliente', $arguments['id']);
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
						$this->response->result = $cliente->result;
						$this->response->errors = $cliente->errors;
						$this->response->state = $this->model->transaction->regresaTransaccion();
						$this->response->SetResponse(false, $cliente->message);
					}
				} else {
					$this->response->result = $usuario->result;
					$this->response->errors = $usuario->errors;
					$this->response->state = $this->model->transaction->regresaTransaccion();
					$this->response->SetResponse(false, $usuario->message);
				}

			return $response->withJson($this->response);
		})->add( new MiddlewareToken() );
		
		$this->put('del/{id}', function($request, $response, $arguments) {
			require_once './core/defines.php';
			$this->response = new Response();
			$this->response->state = $this->model->transaction->iniciaTransaccion();

			$cliente = $this->model->usuario->del($arguments['id']);
			if($cliente->response) {
				$seg_log = $this->model->seg_log->add('Cliente dado de baja', 'cliente', $arguments['id']);
				if($seg_log->response) {
					$this->response->result = $cliente->result;
					$this->response->state = $this->model->transaction->confirmaTransaccion();
					$this->response->SetResponse(true);
				} else {
					$this->response->result = $seg_log->result;
					$this->response->errors = $seg_log->errors;
					$this->response->state = $this->model->transaction->regresaTransaccion();
					$this->response->SetResponse(false, $seg_log->message);
				}
			}

			return $response->withJson($this->response);
		})->add( new MiddlewareToken() );
	});
?>