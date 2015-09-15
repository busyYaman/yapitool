<?php

class ErrorLine
{
	private $current_line_number		= 0;
	private $error_log_list				= array();

	//////////////////////////////////////////////////////////////////////////////
	// error line control
	//////////////////////////////////////////////////////////////////////////////

	public function get_error_log()
	{
		return $this->error_log_list;
	}

	public function set_error_line_number($line)
	{
		$this->current_cvs_line_number	= $line;
	}

	public function get_error_log_by_line($line_number=NULL)
	{
		if(empty($line_number))
			$line_number				= $this->current_cvs_line_number;

		return
			(!empty($this->error_log_list[$line_number]))?
				$this->error_log_list[$line_number]:
				NULL;
	}

	public function insert_error_log($log_message, $line_number=NULL)
	{
		if(empty($line_number))
			$line_number				= $this->current_cvs_line_number;

		$this->error_log_list[$line_number]
										= $log_message;
	}

	public function reset_error_log()
	{
		$this->error_log_list			= array();
	}
};
