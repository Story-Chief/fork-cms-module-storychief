<?php

namespace Frontend\Modules\Storychief\Command;

final class UpdateArticle
{
	/**
	 * @var array
	 */
	public $payload;

	/**
	 * @param array $payload
	 */
	public function __construct(array $payload)
	{

		$this->payload = $payload;
	}
}
