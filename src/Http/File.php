<?php


namespace Sparkle\Http;


class File implements \JsonSerializable
{
    const UPLOAD_ERR_OK = 0;
    const UPLOAD_ERR_INI_SIZE = 1;
    const UPLOAD_ERR_FORM_SIZE = 2;
    const UPLOAD_ERR_PARTIAL = 3;
    const UPLOAD_ERR_NO_FILE = 4;
    const UPLOAD_ERR_NO_TMP_DIR = 6;
    const UPLOAD_ERR_CANT_WRITE = 7;
    const UPLOAD_ERR_EXTENSION = 8;

    const ERRORS = array(
        0 => 'There is no error, the file uploaded with success',
        1 => 'The uploaded file exceeds the upload_max_filesize directive in php.ini',
        2 => 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form',
        3 => 'The uploaded file was only partially uploaded',
        4 => 'No file was uploaded',
        6 => 'Missing a temporary folder',
        7 => 'Failed to write file to disk.',
        8 => 'A PHP extension stopped the file upload.',
    );

    private string $name = '';
    /**
     * @var mixed
     */
    private string $type = '';
    /**
     * @var mixed
     */
    private string $tmp_name = '';
    /**
     * @var mixed
     */
    private int $error;
    /**
     * @var mixed
     */
    private int $size;

    public function __construct($file)
    {
        $this->name = $file['name'];
        $this->type = $file['type'];
        $this->tmp_name = $file['tmp_name'];
        $this->error = $file['error'];
        $this->size = $file['size'];
    }

    /**
     * @return mixed
     */
    public function getSize()
    {
        return $this->size;
    }

    /**
     * @return mixed
     */
    public function getError()
    {
        return $this->error;
    }

    /**
     * @return mixed
     */
    public function getErrorDescription()
    {
        return self::ERRORS[$this->error];
    }

    /**
     * @return mixed
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @return mixed|string
     */
    public function getName()
    {
        return $this->name;
    }

    public function hasFile()
    {
        return $this->error === self::UPLOAD_ERR_OK;
    }

    public function moveTo($dest){
        return move_uploaded_file($this->tmp_name, $dest);
    }

    public function jsonSerialize()
    {
        return [
            'name' => $this->name,
            'type' => $this->type,
            'size' => $this->size,
            'error' => $this->error,
            'description' => self::ERRORS[$this->error],
        ];
    }
}
