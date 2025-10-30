<?php
	namespace App\Model;

	use PDOException;
	use App\Lib\Response;
	use Slim\Http\UploadedFile;

	class EstadoResultadosModel {
		private $db;
		private $table = 'estado_resultados'; 
		private $response;
		
		public function __CONSTRUCT($db) {
			$this->db = $db;
			$this->response = new Response();
		}

		public function get($id_estado_resultados) {
			$this->response->result = $this->db
				->from($this->table)
				->where('id_estado_resultados', $id_estado_resultados)
				->fetch();

			if($this->response->result) $this->response->SetResponse(true,' ');
			else $this->response->SetResponse(false,'no existe el registro');

			return $this->response;
		}

		public function getAll($page, $limit, $since=null, $to=null) {
			$start = $page * $limit;
			$conditionDate = "true";
			if(isset($since) && isset($to)) {
				$since = explode('-', $since);
				$since = date('Y-m-d', mktime(0, 0, 0, $since[0], 1, $since[1]));
				$to = explode('-', $to);
				$to = date('Y-m-d', mktime(23, 59, 59, $to[0], date('d', mktime(0, 0, 0, $to[0]+1, 0, $to[1])), $to[1]));
				$conditionDate = "cast(concat(anio, '-', mes, '-', 01) as Date) between '$since' and '$to'";
				// print_r($conditionDate);
			}

			if(intval($limit>0)) {
				$this->response->result = $this->db
					->from($this->table)
					->select("DATE_FORMAT(editado, '%d-%m-%Y') AS editado")
					->select("@ventas_netas := ing_ventas_brutas + ing_devoluciones + ing_descuentos AS ventas_netas")
					->select("@util_bruta := @ventas_netas - ing_costo_ventas AS util_bruta")
					->select("@sum_operativos := gto_op_ventas + gto_op_sueldos + gto_op_comisiones + gto_op_entrega + gto_op_mercadotecnia + gto_op_viajes + gto_op_viaticos + gto_op_otros AS sum_operativos")
					->select("@sum_administrativos := gto_admin_salarios + gto_admin_compensaciones + gto_admin_isn + gto_admin_seguros + gto_admin_renta + gto_admin_electricidad + gto_admin_telefono + gto_admin_agua + gto_admin_celular + gto_admin_papeleria + gto_admin_mensajeria + gto_admin_soporte + gto_admin_membresias + gto_admin_mobiliario AS sum_administrativos")
					->select("@sum_financieros := gto_finan_financieros AS sum_financieros")
					->select("@gastos_operativos := @sum_operativos + @sum_administrativos AS gastos_operativos")
					->select("@util_operativa := @util_bruta - @gastos_operativos AS utilidad_operativa")
					->select("@util_neta := @util_operativa - @sum_financieros - uti_isr_descuento AS utilidad_neta")
					->where($conditionDate)
					->orderBy('anio desc, mes desc, id desc')
					->limit("$start, $limit")
					->fetchAll();
			} else {
				$this->response->result = $this->db
					->from($this->table)
					->select("DATE_FORMAT(editado, '%d-%m-%Y') AS editado")
					->select("@ventas_netas := ing_ventas_brutas + ing_devoluciones + ing_descuentos AS ventas_netas")
					->select("@util_bruta := @ventas_netas - ing_costo_ventas AS util_bruta")
					->select("@sum_operativos := gto_op_ventas + gto_op_sueldos + gto_op_comisiones + gto_op_entrega + gto_op_mercadotecnia + gto_op_viajes + gto_op_viaticos + gto_op_otros AS sum_operativos")
					->select("@sum_administrativos := gto_admin_salarios + gto_admin_compensaciones + gto_admin_isn + gto_admin_seguros + gto_admin_renta + gto_admin_electricidad + gto_admin_telefono + gto_admin_agua + gto_admin_celular + gto_admin_papeleria + gto_admin_mensajeria + gto_admin_soporte + gto_admin_membresias + gto_admin_mobiliario AS sum_administrativos")
					->select("@sum_financieros := gto_finan_financieros AS sum_financieros")
					->select("@gastos_operativos := @sum_operativos + @sum_administrativos AS gastos_operativos")
					->select("@util_operativa := @util_bruta - @gastos_operativos AS utilidad_operativa")
					->select("@util_neta := @util_operativa - @sum_financieros - uti_isr_descuento AS utilidad_neta")
					->where($conditionDate)
					->orderBy('anio desc, mes desc, id desc')
					->fetchAll();
			}

			$this->response->accumulated = $this->db
				->from($this->table)
				->select("DATE_FORMAT(editado, '%d-%m-%Y') AS editado")
				->select("SUM(ing_ventas_brutas) AS ing_ventas_brutas, SUM(ing_devoluciones) AS ing_devoluciones, SUM(ing_descuentos) AS ing_descuentos, SUM(ing_costo_ventas) AS ing_costo_ventas, SUM(gto_admin_salarios) AS gto_admin_salarios, SUM(gto_admin_compensaciones) AS gto_admin_compensaciones, SUM(gto_admin_isn) AS gto_admin_isn, SUM(gto_admin_seguros) AS gto_admin_seguros, SUM(gto_admin_renta) AS gto_admin_renta, SUM(gto_admin_electricidad) AS gto_admin_electricidad, SUM(gto_admin_telefono) AS gto_admin_telefono, SUM(gto_admin_agua) AS gto_admin_agua, SUM(gto_admin_celular) AS gto_admin_celular, SUM(gto_admin_papeleria) AS gto_admin_papeleria, SUM(gto_admin_mensajeria) AS gto_admin_mensajeria, SUM(gto_admin_soporte) AS gto_admin_soporte, SUM(gto_admin_membresias) AS gto_admin_membresias, SUM(gto_admin_mobiliario) AS gto_admin_mobiliario, SUM(gto_op_ventas) AS gto_op_ventas, SUM(gto_op_sueldos) AS gto_op_sueldos, SUM(gto_op_comisiones) AS gto_op_comisiones, SUM(gto_op_entrega) AS gto_op_entrega, SUM(gto_op_mercadotecnia) AS gto_op_mercadotecnia, SUM(gto_op_viaticos) AS gto_op_viaticos, SUM(gto_op_viajes) AS gto_op_viajes, SUM(gto_op_otros) AS gto_op_otros, SUM(gto_finan_financieros) AS gto_finan_financieros, SUM(uti_antes_impuestos) AS uti_antes_impuestos, SUM(uti_isr_porcentaje) AS uti_isr_porcentaje, SUM(uti_isr_descuento) AS uti_isr_descuento")
				->select("@ventas_netas := SUM(ing_ventas_brutas) + SUM(ing_devoluciones) + SUM(ing_descuentos) AS ventas_netas")
				->select("@util_bruta := SUM(ing_ventas_brutas) + SUM(ing_devoluciones) + SUM(ing_descuentos) - SUM(ing_costo_ventas) AS util_bruta")
				->select("@sum_operativos := SUM(gto_op_ventas) + SUM(gto_op_sueldos) + SUM(gto_op_comisiones) + SUM(gto_op_entrega) + SUM(gto_op_mercadotecnia) + SUM(gto_op_viajes) + SUM(gto_op_viaticos) + SUM(gto_op_otros) AS sum_operativos")
				->select("@sum_administrativos := SUM(gto_admin_salarios) + SUM(gto_admin_compensaciones) + SUM(gto_admin_isn) + SUM(gto_admin_seguros) + SUM(gto_admin_renta) + SUM(gto_admin_electricidad) + SUM(gto_admin_telefono) + SUM(gto_admin_agua) + SUM(gto_admin_celular) + SUM(gto_admin_papeleria) + SUM(gto_admin_mensajeria) + SUM(gto_admin_soporte) + SUM(gto_admin_membresias) + SUM(gto_admin_mobiliario) AS sum_administrativos")
				->select("@sum_financieros := SUM(gto_finan_financieros) AS sum_financieros")
				->select("@gastos_operativos := SUM(gto_op_ventas) + SUM(gto_op_sueldos) + SUM(gto_op_comisiones) + SUM(gto_op_entrega) + SUM(gto_op_mercadotecnia) + SUM(gto_op_viajes) + SUM(gto_op_viaticos) + SUM(gto_op_otros) + SUM(gto_admin_salarios) + SUM(gto_admin_compensaciones) + SUM(gto_admin_isn) + SUM(gto_admin_seguros) + SUM(gto_admin_renta) + SUM(gto_admin_electricidad) + SUM(gto_admin_telefono) + SUM(gto_admin_agua) + SUM(gto_admin_celular) + SUM(gto_admin_papeleria) + SUM(gto_admin_mensajeria) + SUM(gto_admin_soporte) + SUM(gto_admin_membresias) + SUM(gto_admin_mobiliario) AS gastos_operativos")
				->select("@util_operativa := SUM(ing_ventas_brutas) + SUM(ing_devoluciones) + SUM(ing_descuentos) - SUM(ing_costo_ventas) - (SUM(gto_op_ventas) + SUM(gto_op_sueldos) + SUM(gto_op_comisiones) + SUM(gto_op_entrega) + SUM(gto_op_mercadotecnia) + SUM(gto_op_viajes) + SUM(gto_op_viaticos) + SUM(gto_op_otros) + SUM(gto_admin_salarios) + SUM(gto_admin_compensaciones) + SUM(gto_admin_isn) + SUM(gto_admin_seguros) + SUM(gto_admin_renta) + SUM(gto_admin_electricidad) + SUM(gto_admin_telefono) + SUM(gto_admin_agua) + SUM(gto_admin_celular) + SUM(gto_admin_papeleria) + SUM(gto_admin_mensajeria) + SUM(gto_admin_soporte) + SUM(gto_admin_membresias) + SUM(gto_admin_mobiliario)) AS utilidad_operativa")
				->select("@util_neta := SUM(ing_ventas_brutas) + SUM(ing_devoluciones) + SUM(ing_descuentos) - SUM(ing_costo_ventas) - (SUM(gto_op_ventas) + SUM(gto_op_sueldos) + SUM(gto_op_comisiones) + SUM(gto_op_entrega) + SUM(gto_op_mercadotecnia) + SUM(gto_op_viajes) + SUM(gto_op_viaticos) + SUM(gto_op_otros) + SUM(gto_admin_salarios) + SUM(gto_admin_compensaciones) + SUM(gto_admin_isn) + SUM(gto_admin_seguros) + SUM(gto_admin_renta) + SUM(gto_admin_electricidad) + SUM(gto_admin_telefono) + SUM(gto_admin_agua) + SUM(gto_admin_celular) + SUM(gto_admin_papeleria) + SUM(gto_admin_mensajeria) + SUM(gto_admin_soporte) + SUM(gto_admin_membresias) + SUM(gto_admin_mobiliario)) - SUM(gto_finan_financieros) - SUM(uti_isr_descuento) AS utilidad_neta")
				->where($conditionDate)
				->fetch();

			$this->response->total = $this->db
				->from($this->table)
				->select(null)->select('COUNT(*) Total')
				->where($conditionDate)
				->fetch()
				->Total;

			return $this->response->SetResponse(true);
		}

		public function add($data) {
			try {
				$this->response->result = $this->db
					->insertInto($this->table, $data)
					->execute();

				if($this->response->result) $this->response->SetResponse(true, 'id del registro: '.$this->response->result);    
				else $this->response->SetResponse(false, 'no se inserto el registro');

			} catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, 'catch: add model estado_resultados');
			}

			return $this->response;
		}
		
		public function edit($data, $id_estado_resultados) {
			try {
				$this->response->result = $this->db
					->update($this->table, $data)
					->where('id_estado_resultados', $id_estado_resultados)
					->execute();

				if($this->response->result) $this->response->SetResponse(true, "id actualizado: $id_estado_resultados");
				else $this->response->SetResponse(false, 'no se actualizo el registro');

			} catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, 'catch: edit model estado_resultados');
			}
				
			return $this->response;
		}

		public function del($id_estado_resultados) {
			try {
				$data['status'] = 0;
				$this->response->result = $this->db
					->update($this->table, $data)
					->where('id_estado_resultados', $id_estado_resultados)
					->execute();

				if($this->response->result) $this->response->SetResponse(true, "id baja: $id_estado_resultados");
				else $this->response->SetResponse(false, 'no se elimino el registro');

			} catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, 'catch: del model estado_resultados');
			}
		}

		public function getByDate($year, $month) {
			$this->response->result = $this->db
				->from($this->table)
				->select("DATE_FORMAT(editado, '%d-%m-%Y') AS editado")
				->where('anio', $year)
				->where('mes', $month)
				->fetch();

			if($this->response->result) $this->response->SetResponse(true);
			else $this->response->SetResponse(false);

			return $this->response;
		}
	}
?>