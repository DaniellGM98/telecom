<?php
	use App\Lib\Response,
		App\Lib\MiddlewareToken,
		PHPMailer\PHPMailer\Exception;
use Envms\FluentPDO\Literal;

$app->group('/empleado/', function() use ($app) {
		$this->get('', function($request, $response, $arguments) {
			return $res->withHeader('Content-type', 'text/html')->write('Soy ruta de empleado');
		});

		$this->get('get/{id_empleado}', function($request, $response, $arguments) {
			return $response->withJson($this->model->empleado->get($arguments['id_empleado']));
		});

		$this->get('getBySucursal/{sucursal_id}', function($request, $response, $arguments) {
			return $response->withJson($this->model->empleado->getBySucursal($arguments['sucursal_id']));
		});

		$this->get('find/{filtro}', function($request, $response, $arguments) {
			return $response->withJson($this->model->empleado->find($arguments['filtro']));
		});

		$this->get('getAll/[{pagina}/{limite}[/{filtro}]]', function($request, $response, $arguments) {
			$arguments['pagina'] = isset($arguments['pagina'])? $arguments['pagina']: 0;
			$arguments['limite'] = isset($arguments['limite'])? $arguments['limite']: 0;
			$arguments['filtro'] = isset($arguments['filtro'])? $arguments['filtro']: 0;
			return $response->withJson($this->model->empleado->getAll($arguments['pagina'], $arguments['limite'], $arguments['filtro']));
		});

		$this->get('getAllBusca/{pagina}/{limite}/{filtro}', function($request, $response, $arguments) {
			$arguments['filtro'] = isset($arguments['filtro'])? $arguments['filtro']: 0;
			return $response->withJson($this->model->empleado->getAll($arguments['pagina'], $arguments['limite'], $arguments['filtro']));
		});

		$this->post('add/', function($request, $response, $arguments) {
			require_once './core/defines.php';
			$this->response = new Response();
			$this->response->state = $this->model->transaction->iniciaTransaccion();
			$parsedBody = $request->getParsedBody();
			$sucursal_id = isset($parsedBody['sucursal_id'])? $parsedBody['sucursal_id']: $_SESSION['id_sucursal'];
			if(isset($parsedBody['sucursal_id'])) { unset($parsedBody['sucursal_id']); }

			if(!$this->model->usuario->getByEmail($parsedBody['email'])->response) {
				$parsedBody['codigo'] = intval($parsedBody['status'])==2? getCodigoAleatorio(8): '';
				$password = isset($parsedBody['contrasena'])? $parsedBody['contrasena']: getCodigoAleatorio(8);
				$data = [
					'nombre' => $parsedBody['nombre'],
					'apellidos' => $parsedBody['apellidos'],
					'telefono' => $parsedBody['telefono'],
					'email' => $parsedBody['email'],
					'username' => $parsedBody['username'],
					'contrasena' => md5(sha1($password)),
					'usuario_tipo_id' => $parsedBody['usuario_tipo_id'],
					'codigo' => $parsedBody['codigo'],
					//'status' => $parsedBody['status'],
				];
				$usuario = $this->model->usuario->add($data);
				if($usuario->response) { $id_usuario = $usuario->result;
					$fecha = date('Y-m-d H:i:s');
					if(intval($parsedBody['usuario_tipo_id']) == 1) {
						$acciones = $this->model->seg_accion->getAll()->result;
						foreach($acciones as $accion) {
							$seg_permiso = $this->model->seg_permiso->add(['usuario_id'=>$id_usuario, 'accion_id'=>$accion->id, 'asignacion'=>$fecha]);
							if(!$seg_permiso->response) {
								$this->response->result = $seg_permiso->result;
								$this->response->errors = $seg_permiso->errors;
								$this->response->state = $this->model->transaction->regresaTransaccion();
								return $response->withJson($this->response->SetResponse(false, $seg_permiso->message));
							}
						}
					} else {
						$acciones = [1,28,30,79,80,104,105,106,107,2,15,86,87,7,23,24,37,38,39,40,41,43,44,45,47,95,96,97,100,101,89,90,91,92,93,119,120,121];
						foreach($acciones as $accion) {
							$seg_permiso = $this->model->seg_permiso->add(['usuario_id'=>$id_usuario, 'accion_id'=>$accion, 'asignacion'=>$fecha]);
							if(!$seg_permiso->response) {
								$this->response->result = $seg_permiso->result;
								$this->response->errors = $seg_permiso->errors;
								$this->response->state = $this->model->transaction->regresaTransaccion();
								return $response->withJson($this->response->SetResponse(false, $seg_permiso->message));
							}
						}
					}
					$data = [
						'usuario_id' => $id_usuario,
						// 'sucursal_id' => $sucursal_id
					];
					$empleado = $this->model->empleado->add($data);
					if($empleado->response) {
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
							$this->response->email = $this->model->usuario->sendEmail($parsedBody['email'], $subject, $body);
						}*/

						$seg_log = $this->model->seg_log->add('Registro nuevo empleado', 'empleado', $id_usuario);
						if($seg_log->response) {
							$this->response->result = $id_usuario;
							$this->response->state = $this->model->transaction->confirmaTransaccion();
							$this->response->SetResponse(true);
						} else {
							$this->response->result = $seg_log->result;
							$this->response->errors = $seg_log->errors;
							$this->response->state = $this->model->transaction->regresaTransaccion();
							$this->response->SetResponse(false, $seg_log->message);
						}
					} else {
						$this->response->result = $empleado->result;
						$this->response->errors = $empleado->errors;
						$this->response->state = $this->model->transaction->regresaTransaccion();
						$this->response->SetResponse(false, $empleado->message);
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

		$this->put('edit/{id_empleado}', function($request, $response, $arguments) {
			require_once './core/defines.php';
			$this->response = new Response();
			$this->response->state = $this->model->transaction->iniciaTransaccion();
			$parsedBody = $request->getParsedBody();
			if(isset($parsedBody['sucursal_id'])) { $sucursal_id = $parsedBody['sucursal_id']; unset($parsedBody['sucursal_id']); } else { $sucursal_id = 0; }
			if(isset($parsedBody['contrasena']) && strlen($parsedBody['contrasena'])>0) { $parsedBody['contrasena']=md5(sha1($parsedBody['contrasena'])); }
			elseif(isset($parsedBody['contrasena'])) { unset($parsedBody['contrasena']); }
			$id_usuario = $arguments['id_empleado']; $orgInfo = $this->model->empleado->get($id_usuario)->result;
			$areTheSame = true; foreach($parsedBody as $field => $value) {
				if($orgInfo->$field != $value) {
					$areTheSame = false; break;
				}
			}

			$usuario = $this->model->usuario->edit($parsedBody, $id_usuario);
			if($usuario->response || $areTheSame) {
				if($orgInfo->sucursal_id != $sucursal_id) {
					$empleado = $this->model->empleado->edit(['sucursal_id'=>$sucursal_id], $id_usuario);
					if(!$empleado->response) {
						$this->response->result = $empleado->result;
						$this->response->errors = $empleado->errors;
						$this->response->state = $this->model->transaction->regresaTransaccion();
						return $response->withJson($this->response->SetResponse(false, $empleado->message));
					}
					$this->response->areTheSame = false;
				} else { $this->response->areTheSame = $areTheSame; }

				if(!$this->response->areTheSame) {
					$seg_log = $this->model->seg_log->add('Actualización información empleado', 'empleado', $arguments['id_empleado']);
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
		});
		
		$this->put('del/{id_empleado}', function($request, $response, $arguments) {
			$this->response = new Response();
			$this->response->state = $this->model->transaction->iniciaTransaccion();
			
			$usuario = $this->model->empleado->del($arguments['id_empleado']);
			if($usuario->response) {
				$seg_log = $this->model->seg_log->add('Empleado dado de baja', 'empleado', $arguments['id_empleado']);
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
		});

		$this->get('import/', function($request, $response, $arguments){
			$ultimo = $this->db->from('import')->where('tabla', 'usuario')->fetch();
			$registros = $this->dbOld
				->from('usuario')
				->where('id_usuario > ?',$ultimo->ultimo)
				->orderBy('id_usuario')
				->fetchAll();

			$count = 0;
			foreach ($registros as $reg) {
				$data = [
					'id' => $reg->id_usuario,
					'nombre' => $reg->nombre,
					'apellidos' => $reg->apellidos,
					'telefono' => $reg->telefono,
					'email' => $reg->email,
					'username' => $reg->usuario,
					'contrasena' => md5(sha1($reg->contrasena)),
					'usuario_tipo_id' => $reg->fk_id_tipo_usuario,
				];
				$usuario = $this->model->usuario->add($data);
				if($usuario->response) {
					$id_usuario = $usuario->result;
					if($data['usuario_tipo_id'] == 1){
						$acciones = $this->model->seg_accion->getAll()->result;
						foreach($acciones as $accion)
							$seg_permiso = $this->model->seg_permiso->add(['usuario_id'=>$id_usuario, 'accion_id'=>$accion->id, 'asignacion'=>new Literal('NOW()')]);
						$empleado = $this->model->empleado->add(array('usuario_id' => $id_usuario));
					}else{
						$acciones = [1,28,30,79,80,104,105,106,107,2,15,86,87,7,23,24,37,38,39,40,41,43,44,45,47,95,96,97,100,101,89,90,91,92,93,119,120,121];
						foreach($acciones as $accion)
							$seg_permiso = $this->model->seg_permiso->add(['usuario_id'=>$id_usuario, 'accion_id'=>$accion, 'asignacion'=>new Literal('NOW()')]);
						$empleado = $this->model->empleado->add(array('usuario_id' => $id_usuario, 'sucursal_id' => 1));
					}
					$this->db->update('import', array('ultimo' => $reg->id_usuario))->where('tabla', 'usuario')->execute();
					$count++;
				}
			}
			echo 'Listo se insertaron '.$count.' usuarios';
		});
	})->add( new MiddlewareToken() );
?>