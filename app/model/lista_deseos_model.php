<?php
	namespace App\Model;

	use PDOException;
	use App\Lib\Response;

	class ListaDeseosModel {
		private $db;
		private $table = 'lista_deseos'; 
		private $response;

		public function __CONSTRUCT($db) {
			$this->db = $db;
			$this->response = new Response();
		}

		/***
		 * Función para obtener la información de la lista de deseos mediante el ID
		 * recibe {id_lista_deseos} ID del registro en la tabla
		 * regresa: objeto con la información del registro en la lista de deseos
		 */
		public function get($id_lista_deseos) {
			$this->response->result = $this->db
				->from($this->table)
				->where('id_lista_deseos', $id_lista_deseos)
				->fetch();

			if($this->response->result)	$this->response->SetResponse(true);
			else	$this->response->SetResponse(false, 'No existe ningún registro con ese ID');

			return $this->response;
		}
		/*** Fin de la función */

		/***
		 * Función para obtener todos los registros de la lista de deseos de un mismo producto
		 * recibe {fk_producto} ID del producto
		 * regresa: objeto con la información de todos los registros en la lista de deseos
		 */
		public function getByProducto($fk_producto) {
			$this->response->result = $this->db
				->from($this->table)
				->where('fk_producto', $fk_producto)
				->fetchAll();

			return $this->response->SetResponse(true);
		}
		/*** Fin de la función */

		/***
		 * Función para obtener todos los registros agregados por un mismo cliente mediante su ID
		 * recibe {fk_cliente} ID del cliente
		 * recibe {fk_producto} ID del producto. Si no se proporciona dicho valor, o es 0. Devolverá la lista de todos los productos
		 * regresa: objeto con la información de todos los productos agregados a la lista de deseos por un mismo cliente
		 */
		public function getByCliente($fk_cliente, $fk_producto=0) {
			$this->response->result = $this->db
				->from($this->table)
				->where('fk_cliente', $fk_cliente)
				->where("fk_producto ".($fk_producto==0? '>': '=')." $fk_producto")
				->orderBy('fk_producto')
				->fetchAll();
				
			return $this->response->SetResponse(true);
		}
		/*** Fin de la función */

		/***
		 * Función para agregar un nuevo registro a la base de datos
		 * recibe {data} Arreglo con la información del nuevo registro
		 * regresa ID del nuevo registro
		 * ***/
		public function add($data) {
			try {
				$this->response->result = $this->db
					->insertInto($this->table, $data)
					->execute();

				if($this->response->result)	$this->response->SetResponse(true);
				else { $this->response->SetResponse(false, 'no se inserto el registro'); }

			} catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, "catch: add model lista_deseos: ".$ex->getMessage());
			}

			return $this->response;
		}
		/*** Fin de la función add */

		/***
		 * Función para editar un registro de la lista_deseos mediante su ID
		 * recibe {data} Información de la lista_deseos actualizada
		 * recibe {id_lista_deseos} ID del registro a modificar
		 * ***/
		public function edit($data, $id_lista_deseos) {
			try {
				$this->response->result = $this->db
					->update($this->table, $data)
					->where('id_lista_deseos', $id_lista_deseos)
					->execute();
					
				if($this->response->result)	$this->response->SetResponse(true, 'actualizado');
				else { $this->response->SetResponse(false, 'no se edito el registro'); }
			}catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, "catch: edit model lista_deseos: ".$ex->getMessage());
			}

			return $this->response;
		}
		/*** Fin de la función edit */

		/****
		 * Función para dar de baja un registro de la base de datos
		 * recibe {id_lista_deseos} ID del registro
		 */
		public function del($id_lista_deseos) {
			try{
				$this->response->result = $this->db
					->deleteFrom($this->table)
					->where('id_lista_deseos', $id_lista_deseos)
					->execute();
					
				if($this->response->result!=0)	$this->response->SetResponse(true, 'registro dado de baja');
				else { $this->response->SetResponse(false, 'no se dio de baja el registro'); }
				
			} catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, "catch: del model lista_deseos: ".$ex->getMessage());
			}

			return $this->response;
		}
		/*** Fin de la función del */
	}
	/*** Fin  de la clase ListaDeseosModel */
?>