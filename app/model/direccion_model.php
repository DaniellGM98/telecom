<?php
	namespace App\Model;

	use PDOException;
	use App\Lib\Response;

	class DireccionModel {
		private $db;
		private $table = 'direccion'; 
		private $response;

		public function __CONSTRUCT($db) {
			$this->db = $db;
			$this->response = new Response();
		}

		/***
		 * Función para obtener la información de una dirección mediante su ID
		 * recibe {id_direccion} ID del registro en la base de datos
		 * regresa: objeto con la información de la dirección específica
		 */
		public function get($id_direccion) {
			$direccion = $this->db
				->from($this->table)
				->select("CONCAT(calle, ' ', num_exterior, ', ', municipio, ', ', estado, ', colonia ', colonia, ', cp ', cp) AS direccion")
				->where('id_direccion', $id_direccion)
				->fetch();

			if($direccion) {
				foreach($direccion as $key => $value) {
					$direccion->$key = utf8_decode($value);
				}
				$direccion->nombre = mb_strtoupper($direccion->nombre);
				$direccion->upper_estado = mb_strtoupper($direccion->estado);
				$direccion->upper_municipio = mb_strtoupper($direccion->municipio);
				$direccion->calle = mb_strtoupper($direccion->calle);
				$direccion->num_exterior = mb_strtoupper($direccion->num_exterior);
				$direccion->num_interior = mb_strtoupper($direccion->num_interior);
				$direccion->colonia = mb_strtoupper($direccion->colonia);
				$direccion->referencia = mb_strtoupper($direccion->referencia);
				$direccion->entrecalle = mb_strtoupper($direccion->entrecalle);
				$direccion->nombre_recibe = mb_strtoupper($direccion->nombre_recibe);

				$this->response->result = $direccion;
				$this->response->SetResponse(true);
			}
			else	$this->response->SetResponse(false, 'No existe ninguna dirección con ese ID');
			
			return $this->response;
		}
		/*** Fin de la función */

		/***
		 * Función para obtener todas las direcciones pertenecientes a un usuario específico
		 * recibe {fk_usuario} ID del usuario
		 * recibe {predeterminada} Bándera para saber si regresar únicamente la dirección predeterminada
		 * regresa: objeto con la información de todas las direcciones pertenecientes a dicho usuario
		 */
		public function getByCliente($fk_usuario, $predeterminada=0) {
			$direcciones = $this->db
				->from($this->table)
				->select("CONCAT(calle, ' ', num_exterior, ', ', municipio, ', ', estado, ', colonia ', colonia, ', cp ', cp) AS direccion")
				->where('fk_usuario', $fk_usuario)
				->where("predeterminada >= $predeterminada")
				->where('status', 1)
				->orderBy('predeterminada DESC')
				->fetchAll();

			foreach($direcciones as $keyDireccion => &$direccion) {
				foreach($direccion as $key => $value) {
					$direcciones[$keyDireccion]->$key = utf8_decode($value);
				}
				$direcciones[$keyDireccion]->nombre = mb_strtoupper($direcciones[$keyDireccion]->nombre);
				$direcciones[$keyDireccion]->upper_estado = mb_strtoupper($direcciones[$keyDireccion]->estado);
				$direcciones[$keyDireccion]->upper_municipio = mb_strtoupper($direcciones[$keyDireccion]->municipio);
				$direcciones[$keyDireccion]->calle = mb_strtoupper($direcciones[$keyDireccion]->calle);
				$direcciones[$keyDireccion]->num_exterior = mb_strtoupper($direcciones[$keyDireccion]->num_exterior);
				$direcciones[$keyDireccion]->num_interior = mb_strtoupper($direcciones[$keyDireccion]->num_interior);
				$direcciones[$keyDireccion]->colonia = mb_strtoupper($direcciones[$keyDireccion]->colonia);
				$direcciones[$keyDireccion]->referencia = mb_strtoupper($direcciones[$keyDireccion]->referencia);
				$direcciones[$keyDireccion]->entrecalle = mb_strtoupper($direcciones[$keyDireccion]->entrecalle);
				$direcciones[$keyDireccion]->nombre_recibe = mb_strtoupper($direcciones[$keyDireccion]->nombre_recibe);
			}

			$this->response->result = $direcciones;
			return $this->response->SetResponse(true);
		}
		/*** Fin de la función */

		/***
		 * Función para obtener la dirección mediante el ID del usuario y el nombre de la dirección
		 * recibe {fk_usuario} ID del usuario
		 * recibe {nombre} nombre de la direccion
		 * regresa: registro con la información de la dirección, si es que existe
		 */
		public function getByNombre($fk_usuario, $nombre) {
			$direccion = $this->db
				->from($this->table)
				->select("CONCAT(calle, ' ', num_exterior, ', ', municipio, ', ', estado, ', colonia ', colonia, ', cp ', cp) AS direccion")
				->where('fk_usuario', $fk_usuario)
				->where('nombre', $nombre)
				->where('status', 1)
				->fetch();

			if($direccion) {
				foreach($direccion as $key => $value) {
					$direccion->$key = utf8_decode($value);
				}
				$direccion->nombre = mb_strtoupper($direccion->nombre);
				$direccion->upper_estado = mb_strtoupper($direccion->estado);
				$direccion->upper_municipio = mb_strtoupper($direccion->municipio);
				$direccion->calle = mb_strtoupper($direccion->calle);
				$direccion->num_exterior = mb_strtoupper($direccion->num_exterior);
				$direccion->num_interior = mb_strtoupper($direccion->num_interior);
				$direccion->colonia = mb_strtoupper($direccion->colonia);
				$direccion->referencia = mb_strtoupper($direccion->referencia);
				$direccion->entrecalle = mb_strtoupper($direccion->entrecalle);
				$direccion->nombre_recibe = mb_strtoupper($direccion->nombre_recibe);

				$this->response->result = $direccion;
				$this->response->SetResponse(true);
			}
			else	$this->response->SetResponse(false, "No existe ninguna dirección con el nombre $nombre");

			return $this->response;
		}
		/*** Fin de la función */

		/***
		 * Función para agregar un nuevo registro de dirección en la base de datos
		 * recibe {data} Arreglo con la información de la dirección
		 * regresa: ID del nuevo registro
		 * ***/
		public function add($data) {
			try {
				$this->response->result = $this->db
					->insertInto($this->table, $data)
					->execute();

				if($this->response->result)	$this->response->SetResponse(true, 'id_direccion del registro: '.$this->response->result);
				else { $this->response->SetResponse(false, 'no se inserto el registro'); }

			} catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, 'catch: add model direccion'.$ex->getMessage());
			}

			return $this->response;
		}
		/*** Fin de la función add */

		/***
		 * Función para editar un registro mediante el ID de este
		 * recibe {data} Información de la dirección actualizada
		 * recibe {ID} ID del registro a actualizar en la base de datos
		 * ***/
		public function edit($data, $id_direccion) {
			try {
				$this->response->result = $this->db
					->update($this->table, $data)
					->where('id_direccion', $id_direccion)
					->execute();
					
				if($this->response->result)	$this->response->SetResponse(true, 'id_direccion actualizado: '.$id_direccion);    
				else { $this->response->SetResponse(false, 'no se edito el registro'); }

			}catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, 'catch: edit model direccion'.$ex->getMessage());
			}

			return $this->response;
		}
		/*** Fin de la función edit */

		/***
		 * Función para dar de baja permanentemente una dirección de la base de datos
		 * recibe {ID} ID del registro a dar de baja de la base de datos
		 * ***/
		public function del($id_direccion) {
			try {
				$data = ['status' => 0];
				$this->response->result = $this->db
					->update($this->table, $data)
					->where('id_direccion', $id_direccion)
					->execute();
					
				if($this->response->result)	$this->response->SetResponse(true, 'id_direccion eliminado: '.$id_direccion);    
				else { $this->response->SetResponse(false, 'no se elimino el registro'); }

			}catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, 'catch: del model direccion'.$ex->getMessage());
			}

			return $this->response;
		}
		/*** Fin de la función del */
	}
	/*** Fin  de la clase DireccionModel */
?>