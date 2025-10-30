<?php
	use App\Lib\Response;
	use Envms\FluentPDO\Literal;
	use App\Lib\MiddlewareToken;

	/*** Grupo bajo la ruta venta_historia ***/  
	$app->group('/venta_historia/', function() {
		$this->get('', function($request, $response, $arguments) {
			return $response->withHeader('Content-type', 'text/html')->write('Soy ruta de venta_historia');
		});

		/*** 
		 * Ruta para obtener la información del registro mediante su ID
		 * recibe {id_historia} ID del registro
		 * regresa: objeto con la información de la tabla venta_historia
		 * ***/
		$this->get('get/{id_historia}', function($request, $response, $arguments) {
			return $response->withJson($this->model->venta_historia->get($arguments['id_historia']));
		});
		
		/***
		 * Ruta para agregar un nuevo registro en la base de datos
		 */
		$this->post('add/', function($request, $response, $arguments) {
			require_once './core/defines.php';
			$this->response = new Response();
			$this->response->state = $this->model->transaction->iniciaTransaccion();
			$parsedBody = $request->getParsedBody();
			$parsedBody['fecha'] = new Literal('NOW()');
			$arrEstatus = array('','Confirmado','Procesando','Enviado','Entregado','');

			$resultado = $this->model->venta_historia->add($parsedBody);
			if($resultado->response) {
				$resultado->fecha = date('d/m/Y H:i');
				$resultado->next = $arrEstatus[$parsedBody['status']+1];

				// ENVIAR CORREO 
				if(intval($parsedBody['status']) == 3) {
					if(!isset($_SESSION)) { session_start(); }
					$venta = $this->model->venta->get($parsedBody['fk_venta'])->result;
					$cliente = $this->model->cliente->get($venta->fk_cliente)->result;
					$direccion = $this->model->direccion->get($venta->fk_direccion)->result;
					$fechaVenta = explode('-', $venta->fecha);
					$fechaVenta = $fechaVenta[2].'/'.$fechaVenta[1].'/'.$fechaVenta[0];

					$subject = 'Tu pedido ha sido enviado';
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
													<td class='front' style='font-family: Open Sans, sans-serif; font-size: 30px; mso-line-height-rule:exactly; line-height:48px; font-weight:normal; color: #000000;' align='center'>TU PEDIDO HA SIDO ENVIADO</td>
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
						<table bgcolor='#FFF4EA' width='550' align='center' cellspacing='0' cellpadding='0' border='0' class='mobile-width'>
							<tbody>
								<tr>
									<td height='20'>&nbsp;</td>
								</tr>
								<tr>
									<td width='60'>&nbsp;</td>
									<td align='justify'>	
										El pedido que realizaste el $fechaVenta con folio '$venta->folio' ha sido enviado. A continuaci&oacute;n se muestra un resumen de la orden.
										Para ver la información completa de tu orden da click sobre el siguiente <a href='".URL_ROOT."/order/$venta->folio'>enlace</a>
									</td>
									<td width='20'>&nbsp;</td>
								</tr>
								<tr>
									<td height='20'>&nbsp;</td>
								</tr>
							</tbody>
						</table>
						<table width='600' align='center' cellspacing='0' cellpadding='0' border='0' class='mobile-width'>
							<tbody>
								<tr>
									<td align='center'>	
										<table width='100%' cellspacing='0' cellpadding='0' border='0' class='full-width' >
											<tbody>
												<tr>
													<td height='40'>&nbsp;</td>
												</tr>
											</tbody>
										</table>	
									</td>
								</tr>
							</tbody>
						</table>
						<table width='600' cellspacing='0' cellpadding='0' border='0' class='full-width'>
							<tbody>
								<tr>
									<td width='50'></td>
									<td>
										<table width='280' border='0' align='left' cellpadding='0' cellspacing='0' class='midaling'>
											<tr align='left'>
												<td style='font-size:15px; color: black; mso-line-height-rule:exactly;  line-height:50px; color:#FFFFFF; font-weight:bold; font-family: Open Sans, sans-serif;' mc:edit='section3_title1'>
													<p style='color: #FF0000; line-height: 30px;'><b>DIRECCI&Oacute;N DE ENV&Iacute;O</b></p>
												</td>
											</tr>
											<tr align='left'>
												<td>
													<table border='0' align='left' cellpadding='0' cellspacing='0' class='midaling'>
														<tr align='left' style='line-height: 20px;'>
															<td style='margin: 0; padding: 0;'>
																<p style='font-size:14px; line-height:25px; color:#000000; font-family: Open Sans, sans-serif; margin: 0; padding: 0;'><b>".mb_strtoupper($direccion->nombre_recibe)."</b></p>
															</td>
														</tr>
														<tr align='left' style='line-height: 20px;'>
															<td style='margin: 0; padding: 0;'>
																<p style='font-size:14px; line-height:20px; color:#000000; font-family: Open Sans, sans-serif; margin: 0; padding: 0;'>".mb_strtoupper($direccion->calle).(is_numeric($direccion->num_exterior)? "#$direccion->num_exterior": mb_strtoupper($direccion->num_exterior)).(strlen($direccion->num_interior)>0? " int ".mb_strtoupper($direccion->num_interior): "").", ".mb_strtoupper($direccion->municipio).", ".mb_strtoupper($direccion->estado)."</p>
															</td>
														</tr>
														<tr align='left' style='line-height: 20px;'>
															<td style='margin: 0; padding: 0;'>
																<p style='font-size:14px; line-height:20px; color:#000000; font-family: Open Sans, sans-serif; margin: 0; padding: 0;'>Colonia ".mb_strtoupper($direccion->colonia).", C.P. ".mb_strtoupper($direccion->cp)."</p>
															</td>
														</tr>
														<tr align='left' style='line-height: 20px;'>
															<td style='margin: 0; padding: 0;'>
																<p style='font-size:14px; line-height:20px; color:#000000; font-family: Open Sans, sans-serif; margin: 0; padding: 0;'>Tel&eacute;fono. $direccion->telefono</p>
															</td>
														</tr>
													</table>
												</td>
											</tr>
										</table>
									</td>
								</tr>
								<tr>
									<td height='40'>&nbsp;</td>
								</tr>
								<tr>
									<td width='50'></td>
									<td>
										<table width='500' border='0' align='left' cellpadding='0' cellspacing='0' class='midaling'>
											<tr align='left'>
												<td style='font-size:15px; color: black; mso-line-height-rule:exactly;  line-height:50px; color:#FFFFFF; font-weight:bold; font-family: Open Sans, sans-serif;' mc:edit='section3_title1'>
													<p style='color: #FF0000; line-height: 30px;'><b>CONTENIDO DEL PEDIDO</b></p>
												</td>
											</tr>
											<tr align='left'>
												<td>
													<table bgcolor='#DDDDDD' width='100%' border='0' cellpadding='0' cellspacing='0' class='midaling'>
														<thead>
															<tr align='center' style='line-height: 30px; margin: 0; padding: 0;'>
																<td width='55%' style='margin: 0; padding: 0;'><p style='font-size:16px; color:#000000; font-family: Open Sans, sans-serif; margin: 0; padding: 0;'><b>Producto</b></p></td>
																<td width='15%' style='margin: 0; padding: 0;'><p style='font-size:16px; color:#000000; font-family: Open Sans, sans-serif; margin: 0; padding: 0;'><b>Cantidad</b></p></td>
																<td width='15%' style='margin: 0; padding: 0;'><p style='font-size:16px; color:#000000; font-family: Open Sans, sans-serif; margin: 0; padding: 0;'><b>Precio</b></p></td>
																<td width='15%' style='margin: 0; padding: 0;'><p style='font-size:16px; color:#000000; font-family: Open Sans, sans-serif; margin: 0; padding: 0;'><b>Importe</b></p></td>
															</tr>
														</thead>
														<tbody bgcolor='#FFFFFF'>
						";
						$det_venta = $this->model->det_venta->getByVenta($id_venta)->result;
						foreach($det_venta as $detalle) {
							$det_producto = $this->model->producto->get($detalle->fk_producto)->result;
							$body .= "
															<tr style='line-height: 20px;'>
																<td align='justify' style='border-left: 1px solid #DDDDDD; border-bottom: 1px solid #DDDDDD;'><pre style='font-size: 14px; line-height: 20px; color: #000000; font-family: Open Sans, sans-serif;'> <b>$det_producto->producto</b></pre></td>
																<td align='center' style='border-bottom: 1px solid #DDDDDD;'><p style='font-size: 14px; line-height: 20px; color: #000000; font-family: Open Sans, sans-serif;'>$detalle->cantidad</p></td>
																<td align='center' style='border-bottom: 1px solid #DDDDDD;'><p style='font-size: 14px; line-height: 20px; color: #000000; font-family: Open Sans, sans-serif;'>$ $detalle->precio</p></td>
																<td align='center' style='border-bottom: 1px solid #DDDDDD; border-right: 1px solid #DDDDDD;'><p style='font-size: 14px; line-height: 20px; color: #000000; font-family: Open Sans, sans-serif;'>$ $detalle->importe</p></td>
															</tr>
							";
						}
						$venta_pago = $this->model->venta_pago->getByVenta($parsedBody['fk_venta'])->result;
						$body .= "
														</tbody>
														<tfoot bgcolor='#FFFFFF'>
															<tr style='line-height: 25px;'>
																<td style='font-size: 14px;' colspan='3' align='right'><b>Subtotal: </b></td>
																<td style='font-size: 14px;' align='center'>$ $venta->subtotal</td>
															</tr>
															<tr style='line-height: 25px;'>
																<td style='font-size: 14px;' colspan='3' align='right'><b>Descuento: </b></td>
																<td style='font-size: 14px;' align='center'>$ $venta->descuento</td>
															</tr>
															<tr style='line-height: 25px;'>
																<td style='font-size: 14px;' colspan='3' align='right'><b>Env&iacute;o: </b></td>
																<td style='font-size: 14px;' align='center'>$ 0.00</td>
															</tr>
															<tr style='line-height: 25px;'>
																<td style='font-size: 14px;' colspan='3' align='right'><b>Total: </b></td>
																<td style='font-size: 14px;' align='center'>$ $venta->total </td>
															</tr>
															<tr style='line-height: 25px;'>
																<td style='font-size: 14px;' colspan='3' align='right'><b>M&eacute;todo de Pago: </b></td>
																<td style='font-size: 14px;' align='center'>".(intval($venta_pago->tipo)==6? "Tarjeta": "Paypal")." </td>
															</tr>
														</tfoot>
													</table>
												</td>
											</tr>
										</table>
									</td>
								</tr>
							</tbody>
						</table>
					";
					$resultado->email = $this->model->cliente->sendEmail($_SESSION['cliente']->email, $subject, $body);
				}

				$seg_log = $this->model->seg_log->add('Alta historia venta', 'venta_historia', $resultado->result);
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

			return $response->withJson($resultado);
		});
	})->add( new MiddlewareToken() );
?>