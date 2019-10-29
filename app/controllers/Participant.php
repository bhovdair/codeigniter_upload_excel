<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Participant extends MY_Controller
{

	public function __construct()
	{
		parent::__construct();
	}

	public function index()
	{
		$data['page_title'] = "Participant";

		$this->load->model('Participant_mdl');
		$data['upload_url'] = site_url('Participant/upload');
		$data['download_url'] = site_url('Participant/download');
		$data['pdf_url'] = site_url('participant_template.xlsx');
		$this->template_lib->setData($data);
		$this->template_lib->load('participant/table');
	}

	public function download(){
		//get data
		set_time_limit(0);
		include APPPATH . '/libraries/phpqrcode/qrlib.php';
		$this->load->model('Participant_mdl');
		$list = $this->Participant_mdl->get_list();
		
		$folder_qr = "qr_codes";
		$folder_pdf = "pdf_files";
		if (!file_exists($folder_qr)){
			mkdir($folder_qr);
		}
		if (!file_exists($folder_pdf)) {
			mkdir($folder_pdf);
		}
		
		
		if($list){
			foreach ($list as $key => $value) {
				$data = (array) $value;
				$filename = str_replace(' ', '_', $data['Name']) . "_" . str_replace(' ', '_', $data['CompanyName']) . '.pdf';
				$file_name = $folder_pdf . "/" .  preg_replace('/[^A-Za-z0-9 _ .-]/', '', $filename);
				$qr_code = $folder_qr . "/" . $data['Barcode'] . ".png";
				if (!file_exists($qr_code))
				QRcode::png($data['Barcode'], $qr_code, QR_ECLEVEL_H, 8, 2);
				
				if (!file_exists($file_name)){
					$html = $this->load->view('participant/file_pdf', $data, true);
					$this->load->library('pdf');
					$this->pdf->load_html($html);
					$this->pdf->set_paper('A4', 'portrait');
					$this->pdf->render();
					$output = $this->pdf->output();
					unset($this->pdf);
					file_put_contents($file_name, $output);
				}
				
			}

			//download pdf as zip
			# create new zip opbject
			$zip = new ZipArchive();

			# create a temp file & open it
			// $tmp_file = tempnam('.','');
			$tmp_file = "temp_dl";
			$zip->open($tmp_file, ZipArchive::CREATE);

			# loop through each file


			foreach ($list as $key => $value) {
				$data = (array) $value;
				$filename = str_replace(' ', '_', $data['Name']) . "_" . str_replace(' ', '_', $data['CompanyName']) . '.pdf';
				$file_name = $folder_pdf . "/" .  preg_replace('/[^A-Za-z0-9 _ .-]/', '', $filename);
				#download file
				$download_file = file_get_contents(base_url($file_name));

				#add it to the zip
				$zip->addFromString(basename($file_name), $download_file);
			}



			# close zip
			$zip->close();

			# send the file to the browser as a download
			header('Content-disposition: attachment; filename=participant.zip');
			header('Content-type: application/zip');
			readfile($tmp_file);
			unlink($tmp_file);
		}

		
	}
	
	public function upload()
	{
		$data['page_title'] = "Upload Participant";
		
		$this->load->model('Participant_mdl');
		$data['list_type'] = $this->Participant_mdl->get_participant_type();
		$data['back_url'] = site_url('Participant');

		$this->template_lib->setData($data);
		$this->template_lib->load('participant/upload');
	}

	function upload_file()
	{
		$this->load->model('Participant_mdl');
		$this->load->library('Excel');
		$name = time() . $_FILES['file']['name'];
		$config['upload_path']          = './temp_upload/';
		$config['allowed_types'] 		= 'xls|xlsx';
		$config['max_size'] 			= '100000';
		$config['overwrite'] 			= true;
		$config['file_name'] 			= $name;
		$this->load->library('upload', $config);

		if (!file_exists($config['upload_path']))
			mkdir($config['upload_path']);

		$this->load->library('upload', $config);

		if (!$this->upload->do_upload('file')) {
			$error = array('error' => $this->upload->display_errors());
			echo '<script>
	     	alert("Please choose file!");
	     	location.href= "' . site_url('Participant/upload') . '";
	     	</script>';
		} else {
			$participant_type = $this->input->post('participant_type', true);
			$uploaded_file = $this->upload->data();
			$uploaded = $config['upload_path'] . $uploaded_file['file_name'];

			try {
				$file_type = PHPExcel_IOFactory::identify($uploaded);
				$objReader = PHPExcel_IOFactory::createReader($file_type);
				$objPHPExcel = $objReader->load($uploaded);

				$sheet = $objPHPExcel->getSheet(0);
				$highestRow_sheet = $sheet->getHighestDataRow();
				$i = "1";
				$prefix = 'IMEMS';
				$r = 0;
				$list = [];
				for ($row = 2; $row <= $highestRow_sheet; $row++) {
					$Name = trim($sheet->getCell('B' . $row)->getValue());
					$CompanyName = trim($sheet->getCell('C' . $row)->getValue());
					$milliseconds = round(microtime() * rand() * 100);
					$data = array(
						'Name' => trim(ucwords($Name)),
						'CompanyName' => trim(ucwords($CompanyName)),
						'Barcode' => $prefix . "" . date('Y') . "" . md5($milliseconds),
						'ParticipantTypeId' => $participant_type
					);

					$existing_data = $this->Participant_mdl->get_data_by_param($data);
					if(!$existing_data){
						$list[] = $data;
					}
					
				}

				if($list)
					$this->Participant_mdl->insert($list);

				unlink($uploaded_file['full_path']);
				echo '<script>
					alert("Upload finished!");
					location.href= "' . site_url('Participant/upload') . '";
					</script>';
			} catch (Exception $e) {

				die('Error loading file "' . pathinfo($uploaded, PATHINFO_BASENAME) . '": ' . $e->getMessage());
			}

		}
	}

	
}
