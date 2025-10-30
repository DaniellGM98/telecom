<?php
	namespace App\Model;

	use PDOException;
	use App\Lib\Response;

	class SegPwdRecoverModel {
		private $db;
		private $table = 'seg_pwd_recover'; 
		private $response;

		public function __CONSTRUCT($db) {
			$this->db = $db;
			$this->response = new Response();
		}

		/***
		 * Función para obtener un registro de la base de datos por medio del ID
		 * recibe {id_seg_pwd_recover} ID del registro en la base de datos
		 * regresa: objeto con la información de la solicitud de restablecer contraseña
		 */
		public function get($id_seg_pwd_recover) {
			$this->response->result = $this->db
				->from($this->table)
				->where('id_seg_pwd_recover', $id_seg_pwd_recover)
				->fetch();

			return $this->response->SetResponse(true, ($this->response->result? '': 'No existe registro con ese ID'));
		}
		/*** Fin de la función */

		/***
		 * Función para obtener un registro de la base de datos por medio de la clave generada
		 * recibe {codigo} clave de 8 caracteres generada cuando se da de alta la solicitud
		 * regresa: objeto con la información de la solicitud de restablecer contraseña
		 */
		public function getByCodigo($codigo) {
			$this->response->result = $this->db
				->from($this->table)
				->where('codigo', $codigo)
				->where('visitado', 0)
				->where('usado', 0)
				->orderBy('fecha desc')
				->fetch();

			return $this->response->SetResponse(true, ($this->response->result? '': 'No existe registro con ese codigo'));
		}
		/*** Fin de la función */

		/***
		 * Función para obtener todas las solicitudes de restablecimiento de contraseña por el cliente
		 * recibe {page} número de página
		 * recibe {limit} número máximo de registros por página
		 * recibe {fk_cliente} id_seg_pwd_recover del cliente
		 * recibe opcional {since} fecha inicial desde la cual mostrar registros
		 * recibe opcional {to} fecha final desde la cual mostrar registros
		 * regresa: objeto con el historico de las solicitudes de restablecimiento de contraseña
		 */
		public function getByCliente($page, $limit, $fk_cliente, $since=null, $to=null) {
			$first = ($page - 1) * $limit;
			$this->response->result = $this->db
				->from($this->table)
				->where("fk_cliente", $fk_cliente)
				->where($since!=null? "fecha between '$since' and '$to'": "true")
				->orderBy("fecha desc")
				->limit("$first, $limit")
				->fetchAll();

			$this->response->total = $this->db
				->from($this->table)
				->select(null)->select('COUNT(*) Total')
				->where('fk_cliente', $fk_cliente)
				->where($since!=null? "fecha between '$since' and '$to'": "true")
				->fetch()->Total;

			return $this->response->SetResponse(true);
		}
		/*** Fin de la función */

		/***
		 * Función para agregar un nuevo registro a la base de datos
		 * recibe {data} Arreglo con la información del nuevo registro
		 * regresa: ID del nuevo registro
		 * ***/
		public function add($data) {
			try {
				$this->response->result = $this->db
					->insertInto($this->table, $data)
					->execute();

				if($this->response->result) {
					return $this->response->SetResponse(true, 'id_seg_pwd_recover del registro: '.$this->response->result);
				} else {
					return $this->response->SetResponse(false, 'no se inserto el registro');
				}
			} catch(\PDOException $ex) {
				$this->response->errors = $ex;
				return $this->response->SetResponse(false, 'catch: add model seg_pwd_recover');
			}
		}
		/*** Fin de la función add */

		/***
		 * Función para editar un registro de la tabla mediante el ID del registro
		 * recibe {data} Información del registro actualizada
		 * recibe {ID} ID del registro a actualizar en la base de datos
		 * ***/
		public function edit($data, $id_seg_pwd_recover) {
			try {
				$this->response->result = $this->db
					->update($this->table, $data)
					->where('id_seg_pwd_recover', $id_seg_pwd_recover)
					->execute();
					
				if($this->response->result) {
					return $this->response->SetResponse(true, 'id_seg_pwd_recover actualizado: '.$id_seg_pwd_recover);    
				} else {
					return $this->response->SetResponse(false, 'no se edito el registro');
				}
			}catch(\PDOException $ex) {
				$this->response->errors = $ex;
				return $this->response->SetResponse(false, 'catch: edit model seg_pwd_recover');
			}
		}
		/*** Fin de la función edit */
	}
	/*** Fin  de la clase PwdRecoverModel */
?>