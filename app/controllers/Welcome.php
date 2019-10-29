<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Welcome extends CI_Controller {

	public function __construct()
	{
		parent::__construct();
		$this->load->model('Upload_mdl');
	}

	public function index()
	{
		if($this->input->post()){
			$this->upload_file();
		}
		$this->load->library('parser');
		$output['list'] = $this->Upload_mdl->get();
		$this->parser->parse('welcome_message', $output);
	}

	public function upload_file()
	{
		$this->load->library('Excel');
		$name = time() . $_FILES['fileupload']['name'];
		$config['upload_path']          = './';
		$config['allowed_types'] 		= 'xls|xlsx';
		$config['max_size'] 			= '100000';
		$config['overwrite'] 			= true;
		$config['file_name'] 			= $name;
		$this->load->library('upload', $config);

		if (!file_exists($config['upload_path']))
			mkdir($config['upload_path']);

		$this->load->library('upload', $config);

		if (!$this->upload->do_upload('fileupload')) {
			$error = array('error' => $this->upload->display_errors());
			echo '<script>
	     	alert("Please choose file!");
	     	location.href= "' . site_url('Welcome') . '";
	     	</script>';
		} else {
			$fileupload = $this->input->post('fileupload', true);
			$uploaded_file = $this->upload->data();
			$uploaded = $config['upload_path'] . $uploaded_file['file_name'];

			try {
				$file_type = PHPExcel_IOFactory::identify($uploaded);
				$objReader = PHPExcel_IOFactory::createReader($file_type);
				$objPHPExcel = $objReader->load($uploaded);

				//get sheet index 0
				$sheet = $objPHPExcel->getSheet(0);
				//highest row = get latest row data
				$highestRow_sheet = $sheet->getHighestDataRow();
				//create list array to do batch insert
				$list = [];
				//do looping until highest row reached
				for ($row = 2; $row <= $highestRow_sheet; $row++) {
					$col1 = $sheet->getCell('A' . $row)->getValue();
					$col2 = $sheet->getCell('B' . $row)->getValue();
					$col3 = $sheet->getCell('C' . $row)->getValue();
					$col4 = $sheet->getCell('D' . $row)->getValue();
					$data = array(
							'Column1' => $col1,
							'Column2' => $col2,
							'Column3' => $col3,
							'Column4' => $col4
					);

					//push streamed data to temporary list.
					$list[] = $data;
				}

				//if list not empty, do insert batch
				if ($list)
					$this->Upload_mdl->insert($list);

				unlink($uploaded_file['full_path']);
				echo '<script>
					alert("Upload finished!");
					location.href= "' . site_url('Welcome') . '";
					</script>';
			} catch (Exception $e) {

				die('Error loading file "' . pathinfo($uploaded, PATHINFO_BASENAME) . '": ' . $e->getMessage());
			}
		}
	}

}
