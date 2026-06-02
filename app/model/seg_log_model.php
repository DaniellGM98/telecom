<?php
	namespace App\Model;

	use PDOException;
	use App\Lib\Response;

	class SegLogModel {
		private $db;
		private $table = 'seg_log';
		private $tableS = 'seg_sesion';
		private $response;
		
		public function __CONSTRUCT($db) {
			$this->db = $db;
			$this->response = new Response();
		}

		public function get($id_seg_log) {
			$this->response->result = $this->db
				->from($this->table)
				->where('id_seg_log', $id_seg_log)
				->fetch();

			if($this->response->result) { return $this->response->SetResponse(true); }
			else { return $this->response->SetResponse(false, 'no existe el registro'); }
		}

		public function getAll() {
			$this->response->result = $this->db
				->from($this->table)
				->fetchAll();

			$this->response->total = $this->db
				->from($this->table)
				->select(null)->select('COUNT(*) Total')
				->fetch()
				->Total;

			return $this->response->SetResponse(true);
		}

		public function getByUsuario($fk_usuario, $since=null, $to=null) {
			$this->response->result = $this->db
				->from($this->table)
				->where('fk_usuario', $fk_usuario)
				->where((!is_null($since) && !is_null($to))? "fecha BETWEEN '$since' AND '$to'": "TRUE")
				->fetchAll();

			$this->response->total = $this->db
				->from($this->table)
				->select(null)->select('COUNT(*) AS total')
				->where('fk_usuario', $fk_usuario)
				->where((!is_null($since) && !is_null($to))? "fecha BETWEEN '$since' AND '$to'": "TRUE")
				->fetch()
				->total;

			return $this->response;
		}

		public function add($descripcion, $tabla, $registro) {
			require_once './core/defines.php';
			date_default_timezone_set('America/Mexico_City');
			if(isset($_SESSION['usuario'])) {
				$usuario = $_SESSION['usuario']->id;
				$sesion = $_SESSION['id_sesion'];
			}else{
				$usuario = 1;
				$sesion = 1;
			}
				$fecha = date('Y-m-d H:i:s');
				$data = [
					'usuario_id' => $usuario, 
					'sesion_id' => $sesion, 
					'fecha' => $fecha,
					'descripcion' => $descripcion, 
					'tabla_nombre' => $tabla,
					'tabla_fila' => $registro, 
				];
				try {
					$resultado = $this->db
						->insertInto($this->table, $data)
						->execute();
	
					if($resultado != 0){
						$sesion = $this->db
							->update($this->tableS, ['fin' => $fecha])
							->where('id', $sesion)
							->execute();
						
						$this->response->result = $resultado;
						$this->response->SetResponse(true, "id_seg_log del registro: $resultado");
					}
					else { $this->response->SetResponse(false, 'no se inserto el registro'); }
				} catch(\PDOException $ex) {
					$this->response->errors = $ex;
					$this->response->SetResponse(false, "catch: add model $this->table");
				}
			
			return $this->response;
		}

		public function edit($data, $id_seg_log) {
			try {
				$this->response->result = $this->db
					->update($this->table, $data)
					->where('id_seg_log', $id_seg_log)
					->execute();

				if($this->response->result!=0)	$this->response->SetResponse(true, 'id_seg_log actualizado: '.$id_seg_log);
				else { $this->response->SetResponse(false, 'no se edito el registro'); }

			} catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, "catch: edit model $this->table");
			}

			return $this->response;
		}

		public function del($id_seg_log) {
			try {
				$this->response->result = $this->db
					->deleteFrom($this->table)
					->where('id_seg_log', $id_seg_log)
					->execute();

				if($this->response->result!=0)	$this->response->SetResponse(true, 'id_seg_log eliminado: '.$id_seg_log);
				else { $this->response->SetResponse(false, 'no se elimino el registro'); }

			} catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, "catch: del model $this->table");
			}

			return $this->response;
		}
	}
?>