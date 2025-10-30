<?php
	namespace App\Model;

	use PDOException;
	use App\Lib\Response;

	class EventoModel {
		private $db;
		private $table = 'evento'; 
		private $tableProd = 'producto';
		private $tableE = 'prod_entrada';
		private $tableP = 'prod_precio';
		private $tableEv = 'prod_evento';
		private $tableS = 'prod_stock';
		private $tableV = 'venta';
		private $tableD = 'det_venta';
		private $tableR = 'resena';
		private $response;

		public function __CONSTRUCT($db) {
			$this->db = $db;
			$this->response = new Response();
		}

		/***
		 * Función para obtener el detalle del evento mediante su ID
		 * recibe {id_evento} ID del evento
		 * regresa: objeto con la información del evento
		 */
		public function get($id_evento) {
			$this->response->result = $this->db
				->from($this->table)
				->where('id_evento', $id_evento)
				->fetch();

			if($this->response->result)	$this->response->SetResponse(true);
			else $this->response->SetResponse(false);

			return $this->response;
		}
		/*** Fin de la función */

		/***
		 * Función para obtener la información de todos los eventos donde se encuentre registrado el producto específico
		 * recibe {fk_producto} ID del producto
		 * recibe opcional {start} Fecha inicial permitida para el evento
		 * recibe opcional {end} Fecha final permitida para el evento. Si no se proporciona esta fecha o es NULL. Regresará los eventos que estén activos en la fecha {start}
		 */
		public function getByProducto($fk_producto, $start=null, $end=null) {
			$searchOnDate = (($start!=null)?
								(($end!=null)? 
									"(inicio>='$start' AND fin<='$end') OR ('$start' between inicio AND fin) OR ('$end' between inicio AND fin)":
									"'$start' between inicio AND fin"):
								"true");

			$this->response->result = $this->db
				->from($this->table)
				->select(null)->select("distinct $this->table.*")
				->leftJoin("$this->tableEv on id_evento = fk_evento")
				->where($searchOnDate)
				->where('fk_producto', $fk_producto)
				->where('status', 1)
				->orderBy("inicio desc, fin asc")
				->fetchAll();

			return $this->response->SetResponse(true);
		}
		/*** Fin de la función */

		/***
		 * Función para obtener el detalle de todos los eventos que comparten una misma lista de precios específica
		 * recibe {fk_lista_precio} ID de la lista de precios
		 * regresa: objeto con la información de todos los eventos
		 */
		public function getByListaPrecio($fk_lista_precio) {
			$this->response->result = $this->db
				->from($this->table)
				->where('fk_lista_precio', $fk_lista_precio)
				->where('status', 1)
				->fetchAll();
				
			$this->response->SetResponse(true);
			return $this->response;
		}
		/*** Fin de la función */

		/***
		 * Función para obtener el detalle de todos los eventos que se encuentren en un rango de fechas específicas
		 * recibe {start} fecha inicial
		 * recibe {end} fecha final. Si este valor es nulo, devolverá los eventos que estén activos en la fecha {start}
		 * regresa: objeto con la información de todos los eventos encontrados
		 */
		public function getByDate($start, $end=null) {
			$condition = $end!=null? "(inicio>='$start' AND fin<='$end') OR ('$start' between inicio and fin) OR ('$end' between inicio and fin)": "('$start' between inicio and fin)";
			$this->response->result = $this->db
				->from($this->table)
				->where($condition)
				->where('status', 1)
				->fetchAll();
				
			$this->response->SetResponse(true);
			return $this->response;
		}
		/*** Fin de la función */

		/***
		 * Función para obtener la información del evento con el slug específico
		 * recibe {slug} slug a buscar
		 * regresa: objeto con la información del evento
		 */
		public function getBySlug($slug) {
			$this->response->result = $this->db
				->from($this->table)
				->where('slug', $slug)
				->where('status', 1)
				->fetch();

			if($this->response->result)	$this->response->SetResponse(true);
			else	$this->response->SetResponse(false, 'No existe ningun evento con dicho slug');

			return $this->response;
		}

		/***
		 * Función para obtener la información de todos los eventos
		 * recibe {page} página a visualizar. Si este valor es 0, devolverá todos los registros
		 * recibe {limit} número de registros por página, si el número de páginal es diferente de 0
		 * recibe {nextOrCurrent} bándera para no contemplar los eventos de fechas pasadas. 0 todas las fechas, 1, solo los actuales, 2 contemplar eventos futuros
		 * recibe {order} nombre del campo por el cual se ordenarán los registros, por default se ordenarán aleatoriamente
		 * regresa: objeto con la información de todos los eventos encontrados
		 */
		public function getAll($page=0, $limit=0, $nextOrCurrent=0, $order='RAND()') {
			date_default_timezone_set('America/Mexico_City');
			$curdate = date('Y-m-d');
			$condition = ($nextOrCurrent=='0'? "true": ($nextOrCurrent=='1'? "'$curdate' between inicio and fin": "fin >= '$curdate'"));
			if($page == 0) {
				$events = $this->db
					->from($this->table)
					->where($condition)
					->where('status', 1)
					->fetchAll();
			} else {
				$start = ($page - 1) * $limit;
				$events = $this->db
					->from($this->table)
					->where($condition)
					->where('status', 1)
					->orderBy($order)
					->limit("$start, $limit")
					->fetchAll();
			}

			foreach($events as &$event) {
				$event->image = $this->getImage($event->id_evento)->result;
			}
			$this->response->result = $events;
			
			$this->response->total = $this->db
				->from($this->table)
				->select(null)->select('COUNT(*) AS total')
				->where($condition)
				->where('status', 1)
				->fetch()->total;

			return $this->response->SetResponse(true);
		}
		/*** Fin de la función */

		/***
		 * Función para agregar un nuevo registro a la base de datos
		 * recibe {data} Arreglo con la información del nuevo registro
		 * ***/
		public function add($data) {
			try {
				$this->response->result = $this->db
					->insertInto($this->table, $data)
					->execute();

				if($this->response->result) {
					return $this->response->SetResponse(true);
				} else {
					return $this->response->SetResponse(false, 'no se inserto el registro');
				}
			} catch(\PDOException $ex) {
				$this->response->errors = $ex;
				return $this->response->SetResponse(false, "catch: add model evento: ".$ex->getMessage());
			}
		}
		/*** Fin de la función add */

		/***
		 * Función para editar un registro de evento mediante el ID del registro
		 * recibe {data} Información del evento actualizada
		 * recibe {id_evento} ID del evento
		 * ***/
		public function edit($data, $id_evento) {
			try {
				$this->response->result = $this->db
					->update($this->table, $data)
					->where('id_evento', $id_evento)
					->execute();
					
				if($this->response->result) {
					$this->response->SetResponse(true, 'actualizado');
				} else {
					$this->response->SetResponse(false, 'no se edito el registro');
				}
			}catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, "catch: edit model evento: ".$ex->getMessage());
			}

			return $this->response;
		}
		/*** Fin de la función edit */

		/****
		 * Función para dar de baja un registro de la base de datos
		 * recibe {id_evento} ID del evento
		 */
		public function del($id_evento) {
			try{
				$data = ['status' => 0];
				$this->response->result = $this->db
					->update($this->table, $data)
					->where('id_evento', $id_evento)
					->execute();
					
				if($this->response->result!=0) {
					$this->response->SetResponse(true, 'registro dado de baja');
				} else {
					$this->response->SetResponse(false, 'no se dio de baja el registro');
				}
			} catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, "catch: del model evento: ".$ex->getMessage());
			}

			return $this->response;
		}
		/*** Fin de la función del */

		/***
		 * Función para obtener la ruta donde se encuentra la imagen del evento con el ID específico
		 * recibe {id_evento} ID del evento
		 * regresa: ruta de la imagen del evento
		 * ***/
		public function getImage($id_evento) {
			require_once './core/defines.php';

			$filename = "$id_evento.jpg";
			$filePath = "assets/image/eventos/$filename";
			
			if(file_exists($filePath)) {
				$this->response->result = URL_API."assets/image/eventos/$filename";
				$this->response->SetResponse(true);
			} else {
				$this->response->result = URL_IMG_DEFAULT;
				$this->response->SetResponse(false, "No existe la imagen del evento $id_evento");
			}

			return $this->response->SetResponse(true);
		}
		/*** Fin de la función */
	}
	/*** Fin  de la clase EventoModel */
?>