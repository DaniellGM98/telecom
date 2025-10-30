<?php
	namespace App\Model;

	use PDOException;
	use App\Lib\Response;
	use Envms\FluentPDO\Literal;

	class SucursalModel {
		private $db;
		private $table = 'sucursal'; 
		private $tblSaldo = 'saldo'; 
		private $response;

		public function __CONSTRUCT($db) {
			$this->db = $db;
			$this->response = new Response();
		}

		public function areTheSame($id, $data) {
			$orgData = $this->db
				->from($this->table)
				->where('id', $id)
				->where('status', 1)
				->fetch();

			$this->response->result = true;
			$this->response->SetResponse(true, 'REGISTROS IDENTICOS');
			if($orgData) {
				foreach($data as $field => $value) {
					if($orgData->$field != $value) {
						$this->response->result = false;
						$this->response->SetResponse(true, 'REGISTROS DISTINTOS');
						break;
					}
				}
			} else { $this->response->SetResponse(false, 'NO existe el id ingresado'); }

			return $this->response;
		}

		public function get($id) {
			$this->response->result = $this->db
				->from($this->table)
				->select(NULL)->select("id, nombre, IFNULL(direccion, '') AS direccion, IFNULL(rfc, '') AS rfc, IFNULL(curp, '') AS curp, IFNULL(telefono, '') AS telefono, IFNULL(correo, '') AS correo, consecutivo_venta, empleado_id, status, mensaje")
				->where('id', $id)
				->fetch();

			if($this->response->result)	$this->response->SetResponse(true);
			else { $this->response->SetResponse(false, 'no existe el registro'); }
			return $this->response;
		}

		public function find($busqueda) {
			$this->response->result = $this->db
				->from($this->table)
				->select(NULL)->select("id, nombre, IFNULL(direccion, '') AS direccion, IFNULL(telefono, '') AS telefono, IFNULL(correo, '') AS correo, consecutivo_venta, empleado_id, status")
				->where("CONCAT_WS(' ', nombre, direccion, telefono, correo) LIKE '%$busqueda%'")
				->fetchAll();

			return $this->response->SetResponse(true);
		}

		public function getAll() {
			$this->response->result = $this->db
				->from($this->table)
				->select(NULL)->select("id, nombre, IFNULL(direccion, '') AS direccion, IFNULL(telefono, '') AS telefono, IFNULL(correo, '') AS correo, consecutivo_venta, empleado_id, status")
				->where('status', 1)
				->orderBy('nombre')
				->fetchAll();

			$this->response->total = $this->db
				->from($this->table)
				->select(null)->select('COUNT(*) Total')
				->where('status', 1)
				->fetch()
				->Total;

			return $this->response->SetResponse(true);
		}

		public function getAllBusca($pagina=0, $limite=0, $busqueda=0) {
			if(intval($busqueda)==0) { $busqueda = '_'; }
			if(intval($limite)==0) {
				$this->response->result = $this->db
					->from($this->table)
					->select(NULL)->select("id, nombre, IFNULL(direccion, '') AS direccion, IFNULL(rfc, '') AS rfc, IFNULL(curp, '') AS curp, IFNULL(telefono, '') AS telefono, IFNULL(correo, '') AS correo, consecutivo_venta, empleado_id, status, mensaje")
					->where("CONCAT_WS(' ', nombre, direccion) LIKE '%$busqueda%'")
					->where('status', 1)
					->orderBy('nombre')
					->fetchAll();
			} else {
				$inicial = $pagina * $limite;
				$this->response->result = $this->db
					->from($this->table)
					->select(NULL)->select("id, nombre, IFNULL(direccion, '') AS direccion, IFNULL(rfc, '') AS rfc, IFNULL(curp, '') AS curp, IFNULL(telefono, '') AS telefono, IFNULL(correo, '') AS correo, consecutivo_venta, empleado_id, status, mensaje")
					->where("CONCAT_WS(' ', nombre, direccion) LIKE '%$busqueda%'")
					->where('status', 1)
					->limit("$inicial, $limite")
					->orderBy('nombre')
					->fetchAll();
			}

			$this->response->total = $this->db
				->from($this->table)
				->select(null)->select('COUNT(*) Total')
				->where("CONCAT_WS(' ', nombre, direccion) LIKE '%$busqueda%'")
				->where('status', 1)
				->fetch()
				->Total;

			return $this->response->SetResponse(true);
		}

		public function checkPassword($id, $contrasena) {
			$this->response->result = $this->db
				->from($this->table)
				->where('id', $id)
				->where('contrasena', md5(sha1($contrasena)))
				->where('status', 1)
				->fetch();

			if($this->response->result) { $this->response->SetResponse(true); }
			else { $this->response->SetResponse(false, 'NO coincide la contraseña'); }
			return $this->response;
		}

		public function add($data) {
			try{
				$this->response->result = $this->db
					->insertInto($this->table, $data)
					->execute();

				if($this->response->result!=0)	$this->response->SetResponse(true, 'id del registro: '.$this->response->result);    
				else { $this->response->SetResponse(false, 'no se inserto el registro'); }

			} catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, 'catch: add model sucursal');
			}

			return $this->response;
		}

		public function edit($data, $id) {
			try{
				$this->response->result = $this->db
					->update($this->table, $data)
					->where('id', $id)
					->execute();

				if($this->response->result!=0) { $this->response->SetResponse(true, "id actualizado: $id"); }    
				else { $this->response->SetResponse(false, 'no se edito el registro'); }

			}catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, 'catch: edit model sucursal');
			}

			return $this->response;
		}

		public function del($id) {
			try{
				$data['status'] = 0;
				$this->response->result = $this->db
					->update($this->table, $data)
					->where('id', $id)
					->execute();

				if($this->response->result!=0) { $this->response->SetResponse(true, "id baja: $id");  }
				else { $this->response->SetResponse(false, 'no se dio de baja el registro'); }

			} catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, 'catch: del model sucursal');
			}

			return $this->response;
		}

		public function liberarSucursal($id) {
			try{
				$this->response->result = $this->db->getPdo()->query("UPDATE $this->table SET empleado_id = NULL WHERE id = $id")->execute();
				if($this->response->result) { $this->response->SetResponse(true); }
				else { $this->response->SetResponse(false, 'no se edito el registro'); }

			}catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, 'catch: edit model sucursal');
			}

			return $this->response;
		}

		public function loginSucursal($id, $password) {
			$this->response->result = $this->db
				->from($this->table)
				->where('id', $id)
				->where('contrasena', md5(sha1($password)))
				->fetch();

			if($this->response->result) { $this->response->SetResponse(true); }
			else { $this->response->SetResponse(false, 'La contraseña NO coincide'); }
			return $this->response;
		}

		public function getSaldo($suc){
			$res = $this->db->from($this->tblSaldo)
							->where('sucursal_id', $suc)
							->where('fecha',date('Y-m-d'))
							->fetch();

			if(!is_object($res)){
				$data = array('sucursal_id' => $suc, 'fecha' => new Literal('NOW()'));
				$this->db->insertInto($this->tblSaldo, $data)->execute();
				$inicial = '0.00'; $final = '0.00'; $status = 1;
			}else{
				$inicial = $res->inicial; $final = $res->final; $status = $res->status;
			}

			return array('inicial' => $inicial, 'final' => $final, 'status' => $status);
		}

		public function setSaldo($data, $suc) {
			try{
				$this->response->result = $this->db
					->update($this->tblSaldo, $data)
					->where('sucursal_id', $suc)
					->where('fecha',date('Y-m-d'))
					->execute();

				if($this->response->result!=0) { $this->response->SetResponse(true, "id actualizado: $suc"); }    
				else { $this->response->SetResponse(false, 'no se edito el registro'); }

			}catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, 'catch: edit model saldo');
			}

			return $this->response;
		}

		public function getSigFolioVenta($sucursal_id) {
			try{
				$folio = $this->db
					->from($this->table)
					->where('id', $sucursal_id)
					->fetch();
				
				if(is_object($folio)) {
					$folio = intval($folio->consecutivo_venta) + 1;
					$updateFolio = $this->db
						->update($this->table, ['consecutivo_venta'=>$folio])
						->where('id', $sucursal_id)
						->execute();
					if($updateFolio!=0) {
						$this->response->result = $folio;
						$this->response->SetResponse(true);
					} else { $this->response->SetResponse(false, 'no se actualizo el nuevo folio en sucursal'); }
				} else { $this->response->SetResponse(false, 'no existe la sucursal'); }
			}catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, 'catch: edit model sucursal');
			}

			return $this->response;
		}

		public function getSigFolioApartado($sucursal_id) {
			try{
				$folio = $this->db
					->from($this->table)
					->where('id', $sucursal_id)
					->fetch();
				
				if(is_object($folio)) {
					$folio = intval($folio->consecutivo_apartado) + 1;
					$updateFolio = $this->db
						->update($this->table, ['consecutivo_apartado'=>$folio])
						->where('id', $sucursal_id)
						->execute();
					if($updateFolio!=0) {
						$this->response->result = $folio;
						$this->response->SetResponse(true);
					} else { $this->response->SetResponse(false, 'no se actualizo el nuevo folio en sucursal'); }
				} else { $this->response->SetResponse(false, 'no existe la sucursal'); }
			}catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, 'catch: edit model sucursal');
			}

			return $this->response;
		}

		public function getSigFolioEntrada($sucursal_id) {
			try{
				$folio = $this->db
					->from($this->table)
					->where('id', $sucursal_id)
					->fetch();
				
				if(is_object($folio)) {
					$folio = intval($folio->consecutivo_entrada) + 1;
					$updateFolio = $this->db
						->update($this->table, ['consecutivo_entrada'=>$folio])
						->where('id', $sucursal_id)
						->execute();
					if($updateFolio!=0) {
						$this->response->result = $folio;
						$this->response->SetResponse(true);
					} else { $this->response->SetResponse(false, 'no se actualizo el nuevo folio en sucursal'); }
				} else { $this->response->SetResponse(false, 'no existe la sucursal'); }
			}catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, 'catch: edit model sucursal');
			}

			return $this->response;
		}

		public function getSigFolioTraspaso($sucursal_id) {
			try{
				$folio = $this->db
					->from($this->table)
					->where('id', $sucursal_id)
					->fetch();
				
				if(is_object($folio)) {
					$folio = intval($folio->consecutivo_traspaso) + 1;
					$updateFolio = $this->db
						->update($this->table, ['consecutivo_traspaso'=>$folio])
						->where('id', $sucursal_id)
						->execute();
					if($updateFolio!=0) {
						$this->response->result = $folio;
						$this->response->SetResponse(true);
					} else { $this->response->SetResponse(false, 'no se actualizo el nuevo folio en sucursal'); }
				} else { $this->response->SetResponse(false, 'no existe la sucursal'); }
			}catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, 'catch: edit model sucursal');
			}

			return $this->response;
		}
	}
?>