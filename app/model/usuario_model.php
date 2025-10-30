<?php
	namespace App\Model;

	use PDOException;
	use App\Lib\Response;
use Envms\FluentPDO\Literal;
use Slim\Http\UploadedFile;
	use PHPMailer\PHPMailer\PHPMailer;
	use PHPMailer\PHPMailer\Exception;

	class UsuarioModel {
		private $db;
		private $table = 'usuario';
		private $tableT = 'usuario_tipo';
		private $tableC = 'cliente';
		private $tableE = 'empleado';
		private $tblSueldo = 'pago';
		// private $tableM = 'seg_modulo';
		// private $tableA = 'seg_accion';
		// private $tableP = 'seg_permiso';
		private $response;
		
		public function __CONSTRUCT($db) {
			require_once './core/defines.php';
			$this->db = $db;
			$this->response = new Response();
		}

		public function getByMD5($md5Value, $campo) {
			$this->response->result = $this->db
				->from($this->table)
				->where("MD5(LOWER($campo))", $md5Value)
				->where('status', 1)
				->fetchAll();
			
			$this->response->total = $this->db
				->from($this->table)
				->select(NULL)->select('COUNT(*) AS total')
				->where("MD5(LOWER($campo))", $md5Value)
				->where('status', 1)
				->fetch()
				->total;

			return $this->response->SetResponse(true);;
		}

		public function find($busqueda, $usuario_tipo_id=0) {
			$busqueda = $busqueda==0? "_": $busqueda;
			$usuarios = $this->db
				->from($this->table)
				->where("CONCAT_WS(' ', nombre, apellidos, telefono, email) LIKE '%$busqueda%'")
				->where("usuario_tipo_id".($usuario_tipo_id==0? ">": "=").$usuario_tipo_id)
				->where("status", 1)
				->fetchAll();
			foreach($usuarios as $usuario) {
				unset($usuario->contrasena);
			}

			$this->response->total = $this->db
				->from($this->table)
				->select(null)->select("COUNT(*) AS total")
				->where("CONCAT_WS(' ', nombre, apellidos, telefono, email) LIKE '%$busqueda%'")
				->where("usuario_tipo_id".($usuario_tipo_id==0? ">": "=").$usuario_tipo_id)
				->where("status", 1)
				->fetch()
				->total;

			$this->response->result = $usuarios;
			return $this->response->SetResponse(true);
		}

		public function get($id) {
			$usuario = $this->db
				->from($this->table)
				->where('id', $id)
				->fetch();

			if($usuario) {
				unset($usuario->contrasena);

				$this->response->result = $usuario;
				$this->response->SetResponse(true);
			} else {
				$this->response->SetResponse(false, 'no existe el registro');
			}

			return $this->response;
		}

		public function getByEmail($email) {
			$usuario = $this->db
				->from($this->table)
				->where('email', $email)
				->where("status > 0")
				->fetch();
			
			if($usuario) {
				unset($usuario->contrasena);
				$this->response->SetResponse(true);
			} else $this->response->SetResponse(false, 'No existe cliente con ese correo');

			$this->response->result = $usuario;
			return $this->response;
		}

		public function getByCodigoVerificacion($codigo) {
			$this->response->result = $this->db
				->from($this->table)
				->where('codigo', $codigo)
				->orderBy('id desc')
				->fetch();

			if($this->response->result)	$this->response->SetResponse(true);
			else	$this->response->SetResponse(false, 'No existe cliente con ese codigo');

			return $this->response;
		}
		/*** Fin de la función */

		public function getAll($pagina, $limite, $usuario_tipo_id, $busqueda=0) {
			$inicial = $pagina * $limite;
			$busqueda = $busqueda==0? "_": $busqueda;
			$usuarios = $this->db
				->from($this->table)
				->where("CONCAT_WS(' ', nombre, apellidos, telefono, email) LIKE '%$busqueda%'")
				->where("usuario_tipo_id".($usuario_tipo_id==0? ">": "=").$usuario_tipo_id)
				->where("status", 1)
				->limit($inicial, $limite)
				->orderBy('apellidos ASC')
				->fetchAll();
			foreach($usuarios as $usuario) {
				unset($usuario->contrasena);
			}

			$this->response->total = $this->db
				->from($this->table)
				->select(null)->select('COUNT(*) Total')
				->where("CONCAT_WS(' ', nombre, apellidos, telefono, email) LIKE '%$busqueda%'")
				->where("usuario_tipo_id".($usuario_tipo_id==0? ">": "=").$usuario_tipo_id)
				->where("status", 1)
				->fetch()->Total;

			$this->response->result = $usuarios;
			return $this->response->SetResponse(true);
		}

		public function getAllbusca($pagina, $limite, $usuario_tipo_id, $busqueda) {
			$busqueda = $busqueda==0? "_": $busqueda;
			if($limite == 0){
				$this->response->result = $this->db
					->from($this->table)
					->select(null)->select("$this->table.id, usuario_tipo_id, $this->table.nombre, apellidos, telefono, email, ultimo_login, $this->table.status, $this->tableT.nombre AS usuario_tipo, username")
					->leftJoin("$this->tableT ON $this->tableT.id = usuario_tipo_id")
					->where(is_int($usuario_tipo_id)? (intval($usuario_tipo_id)==0? "true": "usuario_tipo_id = $usuario_tipo_id"): (is_array($usuario_tipo_id)? "usuario_tipo_id IN (".implode(',', $usuario_tipo_id).")": "usuario_tipo_id IN ($usuario_tipo_id)"))
					->where("CONCAT_WS(' ', $this->table.nombre, apellidos, telefono, email) LIKE '%$busqueda%'")
					->where("$this->table.status != 0")
					->orderBy("status, $this->table.nombre, apellidos ASC")
					->fetchAll();
			} else {
				$inicial = $pagina * $limite;
				$this->response->result = $this->db
					->from($this->table)
					->select(null)->select("$this->table.id, usuario_tipo_id, $this->table.nombre, apellidos, telefono, email, ultimo_login, $this->table.status, $this->tableT.nombre AS usuario_tipo, username")
					->leftJoin("$this->tableT ON $this->tableT.id = usuario_tipo_id")
					->where(is_int($usuario_tipo_id)? (intval($usuario_tipo_id)==0? "true": "usuario_tipo_id = $usuario_tipo_id"): (is_array($usuario_tipo_id)? "usuario_tipo_id IN (".implode(',', $usuario_tipo_id).")": "usuario_tipo_id IN ($usuario_tipo_id)"))
					->where("CONCAT_WS(' ', $this->table.nombre, apellidos, telefono, email) LIKE '%$busqueda%'")
					->where("$this->table.status != 0")
					->orderBy("status, $this->table.nombre, apellidos ASC")
					->limit("$inicial, $limite")
					->fetchAll();
			}

			$this->response->total = $this->db
				->from($this->table)
				->select(null)->select('COUNT(*) Total')
				->where(is_int($usuario_tipo_id)? (intval($usuario_tipo_id)==0? "true": "usuario_tipo_id = $usuario_tipo_id"): (is_array($usuario_tipo_id)? "usuario_tipo_id IN (".implode(',', $usuario_tipo_id).")": "usuario_tipo_id IN ($usuario_tipo_id)"))
				->where("CONCAT_WS(' ', nombre, apellidos, telefono, email) LIKE '%$busqueda%'")
				->where('status != ?', 0)
				->fetch()
				->Total;

			return $this->response->SetResponse(true);
		}
		
		public function add($data){
			try{
				$this->response->result = $this->db
					->insertInto($this->table, $data)
					->execute();

				if($this->response->result!=0) { $this->response->SetResponse(true, 'id del registro: '.$this->response->result); }
				else { $this->response->SetResponse(false, 'no se inserto el registro'); }
			}catch(\PDOException $ex){
				$this->response->errors = $ex;
				$this->response->SetResponse(false, 'catch: add model usuario');
			}

			return $this->response;
		}

		public function edit($data, $id) {
			try {
				$this->response->result = $this->db
					->update($this->table, $data)
					->where('id', $id)
					->execute();

				if($this->response->result != 0) { $this->response->SetResponse(true, "id actualizado: $id"); }
				else { $this->response->SetResponse(false, 'no se edito el registro'); }
			} catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, "catch: edit model $this->table");
			}

			return $this->response;
		}

		public function login($value, $contrasena) {
			$contrasena = md5(sha1($contrasena));
			$usuario = $this->db->getPdo()->query("SELECT * FROM $this->table WHERE (email = '$value' OR username = '$value') AND contrasena = '$contrasena' AND status = 1")->fetch();
			// $usuario = $this->db
			// 	->from($this->table)
			// 	->where("(telecom.$this->table.email = '$value' OR $this->table.username = '$value')")
			// 	->where('contrasena', $contrasena)
			// 	->fetch();
			if (is_object($usuario)) {
				unset($usuario->contrasena);
				if(in_array($usuario->usuario_tipo_id, [1, 2])) { 
					$extra = $this->db
						->from($this->tableE)
						->where('usuario_id', $usuario->id)
						->fetch();
				} else {
					$extra = $this->db
						->from($this->tableC)
						->where('usuario_id', $usuario->id)
						->fetch();
				}
				foreach($extra as $field => $value) { $usuario->$field = $value; }

				$this->ultimoAcceso($usuario->id);
				$newModulos = array();
				if($usuario->usuario_tipo_id != 3){
					$newModulos = $this->getPermisos($usuario->id);
				}
				$this->addSessionLogin($usuario, $newModulos);
				// $_SESSION['usuario'] = $usuario;
					
				$foto="data/foto/".md5($usuario->id).".jpg";
				$usuario->foto = file_exists($foto);
			
				$this->response->SetResponse(true, 'acceso correcto'); 
			} else { $this->response->SetResponse(false, 'verifica tus datos'); }
			
			$this->response->result = $usuario;
			return $this->response;
		}

		public function ultimoAcceso($id) {
			return $this->edit(['ultimo_login' => date("Y-m-d H:i:s")], $id);
		}

		/********* Cambiar password para todos los Usuarios **************/ 
		public function changePassword($data, $id) {
			$old_password = md5(sha1($data['old_password']));
			$password['contrasena'] = md5(sha1($data['new_password']));
			$this->response->result = $this->db
				->update($this->table, $password)
				->where('id', $id)
				->where('contrasena', $old_password)
				->execute();

			if($this->response->result == '1') { $this->response->SetResponse(true, 'contraseña actualizada'); }
			else { $this->response->SetResponse(false, 'no se actualizo'); }

			return $this->response;
		}

		/********* Recuperar password para todos los Usuarios **************/ 
		public function recoveryPass($data) {
			$password['contrasena'] = md5(sha1($data['new_password']));
			$this->response->result = $this->db
				->update($this->table, $password)
				->where('email', $data['email'])
				->execute();
				
			return $this->response->SetResponse(true, 'success');
		}

		function moveUploadedFile($directory, UploadedFile $uploadedFile, $filename) {
			$uploadedFile->moveTo($directory . DIRECTORY_SEPARATOR . $filename);

			return $filename;
		}

		public function sendEmail($emailAddress, $subject, $body, $files=[]) {
			$mail = new PHPMailer(true);
			try {$mail->SMTPDebug = 0;
				$mail->isSMTP();
				$mail->SMTPOptions = array(
					'ssl'=> array(
						'verify_peer' => false,
						'verify_peer_name'=> false,
						'allow_self_signed' => true
					)
				);
				$mail->SMTPAuth = true;
				$mail->SMTPSecure = 'tls';
				$mail->Host = 'smtp.gmail.com';
				$mail->Username = $_SESSION['mail_username'];
				$mail->Password = $_SESSION['mail_pwd'];
				$mail->Port = 587;
				
				//Recipients
				$mail->setFrom($_SESSION['mail_username'], SITE_NAME);
				$mail->addAddress($emailAddress);
				
				//Content
				$mail->isHTML(true);
				$mail->CharSet = 'UTF-8';
				$mail->Subject = $subject;
				$mail->Body    = "
					<html>
						<head>
							<meta content='text/html; charset=UTF-8' http-equiv='Content-Type'>
							<meta content='telephone=no' name='format-detection'>
							<meta content='width=mobile-width; initial-scale=1.0; maximum-scale=1.0; user-scalable=no;' name='viewport'>
							<meta content='IE=9; IE=8; IE=7; IE=EDGE;' http-equiv='X-UA-Compatible'>
							<style type='text/css'>
								/**This is to overwrite Outlook.com’s Embedded CSS************/
								table {border-collapse:separate;}
								a, a:link, a:visited {text-decoration: none; color: #00788a}
								h2,h2 a,h2 a:visited,h3,h3 a,h3 a:visited,h4,h5,h6,.t_cht {color:#000 !important}
								p {margin-bottom: 0}
								.ExternalClass p, .ExternalClass span, .ExternalClass font, .ExternalClass td {line-height: 100%}
								/**This is to center your email in Outlook.com************/
								.ExternalClass {width: 100%;}
								/* General Resets */
								#outlook a {padding:0;}
								body, #body-table {height:100% !important; width:100% !important; margin:0 auto; padding:0; line-height:100%; !important}
								img, a img {border:0; outline:none; text-decoration:none;}
								.image-fix {display:block;}
								table, td {border-collapse:collapse;}
								/* Client Specific Resets */
								.ReadMsgBody {width:100%;} .ExternalClass{width:100%;}
								.ExternalClass, .ExternalClass p, .ExternalClass span, .ExternalClass font, .ExternalClass td, .ExternalClass div {line-height:100% !important;}
								.ExternalClass * {line-height: 100% !important;}
								table, td {mso-table-lspace:0pt; mso-table-rspace:0pt;}
								img {outline: none; border: none; text-decoration: none; -ms-interpolation-mode: bicubic;}
								body, table, td, p, a, li, blockquote {-ms-text-size-adjust:100%; -webkit-text-size-adjust:100%;}
								body.outlook img {width: auto !important;max-width: none !important;}
								/* Start Template Styles */
								/* Main */
								body{ -webkit-text-size-adjust:100%; -ms-text-size-adjust:100%; margin:0; padding:0;}
								body, #body-table {background-color: #000000 margin:0 auto !important;; margin:0 auto !important; text-align:center !important;}
								p {padding:0; margin: 0; line-height: 24px; font-family: Open Sans, sans-serif;}
								a, a:link {color: #1c344d;text-decoration: none !important;}
								.footer-link a, .nav-link a {color: #fff6e5;}
								/* Start Media Queries */
								@media only screen and (max-width: 640px) {
									a[href^='tel'], a[href^='sms'] {text-decoration: none;pointer-events: none;	cursor: default;}
									.mobile_link a[href^='tel'], .mobile_link a[href^='sms'] {text-decoration: default;	pointer-events: auto;cursor: default;}	
									*[class].full-width {width: 100%!important;}
									*[class].mobile-width {width: 440px !important; padding: 0 4px;}
									*[class].content-width {width: 360px!important;}
									*[class].content-width-menu {width: 360px!important;}
									*[class].center {text-align:center !important; height:auto !important;}
									*[class].center-stack {padding-bottom:30px !important; text-align:center !important; height:auto !important;}
									*[class].stack {padding-bottom:30px !important; height: auto !important;}
									*[class].gallery {padding-bottom: 20px!important;}
									*[class].fluid-img {height:auto !important; max-width:600px !important; width: 100% !important;}
									*[class].block {display: block!important;}
									*[class].midaling { width:100% !important; border:none !important; }
								}
								@media only screen and (max-width: 480px) {
									*[class].full-width {width: 100%!important;}
									*[class].mobile-width {width: 320px!important; padding: 0 4px;}
									*[class].content-width {width: 240px!important;}
									*[class].content-width-menu {width: 320px!important;}
									*[class].navlink {font-size:13px !important;}
									*[class].center {text-align:center !important; height:auto !important;}
									*[class].center-stack {padding-bottom:30px !important; text-align:center !important; height:auto !important;}
									*[class].stack {padding-bottom:30px !important; height: auto !important;}
									*[class].gallery {padding-bottom: 20px!important;}
									*[class].fluid-img {height:auto !important; max-width:600px !important; width: 100% !important; min-width:320px !important;}
									*[class].midaling { width:100% !important; border:none !important; }
									*[class].navlink{ width:600px !important; border:none !important; }
								}
								@media only screen and (max-width: 320px) {
									*[class].full-width {width: 100%!important;}
									*[class].mobile-width {width: 100%!important; padding: 0 4px;}
									*[class].content-width {width: 240px!important;}
									*[class].center {text-align:center !important; height:auto !important;}
									*[class].center-stack {padding-bottom:30px !important; text-align:center !important; height:auto !important;}
									*[class].stack {padding-bottom:30px !important; height: auto !important;}
									*[class].gallery {padding-bottom: 20px!important;}
									*[class].fluid-img {height:auto !important; max-width:600px !important; width: 100% !important; min-width:320px !important;}
									*[class].midaling { width:100% !important; border:none !important;}
								}
							</style>
						</head>
						<body bgcolor='#000000' style='background:#000;'>
							<table id='body-table' align='center' width='100%' bgcolor='#e6e5e7' cellspacing='0' cellpadding='0' border='0' style='table-layout:fixed;'>
								<tbody>
									<tr>
										<td valign='top' align='center'>
											<table width='600' bgcolor='#ffffff' align='center' cellspacing='0' cellpadding='0' border='0' class='mobile-width'>
												<tbody>
													<tr>
														<td valign='top' bgcolor='#ffffff' align='center'>
															<table width='600' align='center' cellspacing='0' cellpadding='0' border='0' class='mobile-width'>
																<tbody>
																	<tr>
																		<td align='center'>
																			<table bgcolor='#DF3434' width='100%' cellspacing='0' cellpadding='0' border='0' style=' !important; background-position: center center;background-size: contain;' class='full-width'>
																				<tbody>
																					<tr>
																						<td height='10'></td>
																					</tr>
																				</tbody>
																			</table>
																			<table bgcolor='#243141' width='100%' cellspacing='0' cellpadding='0' border='0'>
																				<tbody>
																					<tr align='center'>
																						<td align='center' height='200'><a href='".URL_ROOT."/'><img src='".URL_ROOT."/assets/image/logo.png' height='200'></a></td>
																					</tr>
																				</tbody>
																			</table>
																			$body
																			<table width='600' align='center' cellspacing='0' cellpadding='0' border='0' class='mobile-width'>
																				<tbody>
																					<tr>
																						<td align='center'>	
																						
																							<!-- Start Space -->
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
																			<table bgcolor='#DF3434' width='100%' cellspacing='0' cellpadding='0' border='0' style=' !important; background-position: center center;background-size: contain;' class='full-width'>
																				<tbody>
																					<tr>
																						<td height='10'></td>
																					</tr>
																				</tbody>
																			</table>
																			<table bgcolor='#243141' width='100%' cellspacing='0' cellpadding='0' border='0' style='background-repeat: no-repeat; !important; background-position: center center;background-size: contain;' class='full-width'>
																				<tbody>
																					<tr>
																						<td height='150'>
																							<table width='160' border='0' align='left' cellpadding='0' cellspacing='0' class='midaling'>
																								<tr align='center'>
																									<td style='font-size:14px; mso-line-height-rule:exactly; line-height:16px; color:#CCCCCC; font-weight:normal; font-family: Open Sans, sans-serif;' mc:edit='section3_text1'>
																										<a href='tel:+527437910211' style='color: #CCCCCC;'> (743) 7910211</a> /<br><a href='tel:+527437910872' style='color: #CCCCCC;'> (743) 7910872</a>
																									</td>
																								</tr>
																							</table>
																							<table width='240' border='0' cellpadding='0' cellspacing='0' align='left' class='midaling'>
																								<tr align='center'>
																									<td style='font-size:14px; mso-line-height-rule:exactly; line-height:16px; color:#CCCCCC; font-weight:normal; font-family: Open Sans, sans-serif;' mc:edit='section3_text1'>
																										Av. Emilio Carranza No. 12, Col. Centro, Zapotlán de Juárez, Hgo. C.P. 42190
																									</td>
																								</tr>
																							</table>
																							<table width='200' border='0' align='left' cellpadding='0' cellspacing='0' class='midaling'>
																								<tr align='center'>
																									<td style='font-size:14px; mso-line-height-rule:exactly; line-height:16px; color:#CCCCCC; font-weight:normal; font-family: Open Sans, sans-serif;' mc:edit='section3_text1'>
																										<a href='mailto:muebleria_cp@hotmail.com' style='color: #CCCCCC;'>muebleria_cp@hotmail.com</a>
																									</td>
																								</tr>
																							</table>
																						</td>
																					</tr>
																				</tbody>
																			</table>
																			<table width='100%' cellspacing='0' cellpadding='0' border='0' style='background-repeat: no-repeat; !important; background-position: center center;background-size: contain;' class='full-width'>
																				<tbody>
																					<tr>
																						<td height='50'>
																							<table width='300' border='0' align='center' cellpadding='0' cellspacing='0' class='midaling'>
																								<tr>
																									<td height='30'>&nbsp;</td>
																								</tr>
																								<tr align='center'>
																									<td style='font-size:25px; mso-line-height-rule:exactly; width: 25px; line-height:16px; color:#264888; font-weight:normal; font-family: Open Sans, sans-serif;' mc:edit='section3_text1'>
																										<a href='https://www.facebook.com/MuebleriaCasaPerez/'><img width='38' height='47' src='http://s3.amazonaws.com/swu-filepicker/LMPMj7JSRoCWypAvzaN3_social_09.gif' alt='facebook' /></a>
																									</td>
																									<td style='font-size:25px; mso-line-height-rule:exactly; width: 25px; line-height:16px; color:#264888; font-weight:normal; font-family: Open Sans, sans-serif;' mc:edit='section3_text1'>
																										<a href='https://twitter.com/MUEBCASAPEREZ'><img width='44' height='47' src='http://s3.amazonaws.com/swu-filepicker/k8D8A7SLRuetZspHxsJk_social_08.gif' alt='twitter' /></a>
																									</td>
																								</tr>
																							</table>
																						</td>
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
																										<td height='50'>&nbsp;</td>
																									</tr>
																									<tr align='center'>
																										<td style='font-size:15px; color:#2c3e50; font-weight:bold; font-family: Open Sans, sans-serif;' mc:edit='section4_title1'>© 2020 Tienda Casa Perez</td>
																									</tr>
																									<tr>
																										<td height='5'></td>
																									</tr>
																									<tr align='center'>
																										<td style='font-size:13px; line-height:16px; color:#95a5a6; font-weight:normal; font-family: Open Sans, sans-serif;' mc:edit='section4_text1'><a href='".URL_ROOT."/'>mcasaperez.com</a></td>
																									</tr>
																									<tr>
																										<td height='20'>&nbsp;</td>
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
														</td>
													</tr>
												</tbody>
											</table>
										</td>
									</tr>
								</tbody>
							</table>
						</body>
					</html>
				";

				for($x=0;$x<count($files);$x++) {
					$filename = explode('/', $files[$x]);
					$filename = $filename[count($filename)-1];
		
					$mail->AddAttachment($files[$x], $filename);
				}
				
				$mail->send();

				$this->response->SetResponse($mail);
			}
			catch (Exception $e) {
				$this->response->SetResponse(false, $e->getMessage());
			}

			return $this->response;
		}

		public function getPermisos($usuario){
			$newModulos = array();
			$modulos = $this->getModulos();
			
			foreach ($modulos as $modulo) {
			  # code...
			  $acciones = $this->getAcciones($usuario,$modulo->id);
			  $contador = count($acciones);
			  $accionesUrl = 0;
			  if($contador>0){
				$modulo->acciones = $acciones;
				foreach ($acciones as $accion) {
				  if($accion->url != '') $accionesUrl++;
				}
				$newModulos[] = $modulo;  
			  }
			  $modulo->accionesUrl = $accionesUrl;
			  
			}//end for each 
			return $newModulos;
		}//end getPermisos 
	  
		  /*** 
			getModulos
		  ***/
		public function getModulos(){
			$r = $this->db->from('seg_modulo')
						->where( 'status', 1)
						->orderBy('id')
						->fetchAll();
	
			return $r;              
		}//end getModulos
	  
		  /*** 
			getAcciones
		  ***/
		public function getAcciones($id_u,$id_m){
			$r = $this->db->from('seg_permiso')->select(null)
						->select(array('seg_accion.id', 'seg_permiso.accion_id', 'seg_accion.nombre', 'seg_accion.url'))
						->innerJoin('seg_accion on seg_accion.id = seg_permiso.accion_id')
						->where('seg_permiso.usuario_id',$id_u)
						->where('seg_accion.seg_modulo_id', $id_m)
						->where('seg_accion.status', 1)
						->fetchAll();
	
			return $r;              
		}
			/*** Crear sesión en login 
	  
			***/
		public function addSessionLogin($usuario, $permisos){
			$browser = $_SERVER['HTTP_USER_AGENT'];
			$ipAddr = $_SERVER['REMOTE_ADDR'];
	
			if (!isset($_SESSION)) {
				ini_set('session.gc_maxlifetime', 18000);
				session_set_cookie_params(18000);
				session_start();
			}
			$_SESSION['ip']  = $ipAddr;
			$_SESSION['navegador']  = $browser;
			$_SESSION['usuario']  = $usuario;
			$_SESSION['sucursal']  = 1;// $usuario->sucursal_id;
			$_SESSION['permisos']  = $permisos;
	
	
		}// end addSessionlogin
	  
		public function del($id){
			$data = [
				'codigo' => '',
				'status' => 0,
			];
			$resultado = $this->db->update($this->table, $data)
								->where('id', $id)
								->execute();
					
			$this->response->result = $resultado;
			return $this->response->SetResponse(true, 'id baja: '.$id);
		}// END del


		public function getVendedores() {
			$usuarios = $this->db
				->from($this->table)
				->select(null)->select('id, CONCAT(nombre," ", apellidos) AS nombre')
				->where("usuario_tipo_id", 2)
				->where("status", 1)
				->orderBy('nombre, apellidos ASC')
				->fetchAll();

			$this->response->result = $usuarios;
			return $this->response->SetResponse(true);
		}

		public function getPago($user){
			$res = $this->db->from($this->tblSueldo)
							->where('usuario_id', $user)
							->fetch();

			if(!is_object($res)){
				$data = array('usuario_id' => $user);
				$this->db->insertInto($this->tblSueldo, $data)->execute();
				$sueldo = '0.00'; $deudas = '0.00';
				$comision = '0.00'; $pago = '0.00';
			}else{
				$sueldo = $res->sueldo; $deudas = $res->deudas;
				$comision = $res->comision; $pago = $res->pago;
			}

			return array('sueldo' => $sueldo, 'comision' => $comision, 'deudas' => $deudas, 'pago' => $pago);
		}

		public function editPago($data, $user) {
			//$data['pago'] = new Literal('(sueldo+comision-abono)');
			try{
				$this->response->result = $this->db
					->update($this->tblSueldo, $data)
					->where('usuario_id', $user)
					->execute();

				if($this->response->result!=0) { $this->response->SetResponse(true, "id actualizado: $user"); }    
				else { $this->response->SetResponse(false, 'no se edito el registro'); }

			}catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, 'catch: edit model pago');
			}

			return $this->response;
		}
	}
?>