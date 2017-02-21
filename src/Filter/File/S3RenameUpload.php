<?php
namespace AwsModule\Filter\File;

use Aws\S3\S3Client;
use AwsModule\Filter\Exception\MissingBucketException;
use Zend\Filter\Exception\RuntimeException as FilterRuntimeException;
use Zend\Filter\File\RenameUpload;
use Zend\Stdlib\ErrorHandler;
use Zend\Stdlib\RequestInterface;
use Zend\Filter\File\Rename;

/**
 * File filter that allow to directly upload to Amazon S3, and optionally rename the file
 */
class S3RenameUpload extends RenameUpload
{
    /**
     * @var S3Client
     */
    protected $client;

    /**
     * @var array
     */
    protected $options = [
        'bucket'               => null,
        'target'               => null,
        'use_upload_name'      => false,
        'use_upload_extension' => false,
        'overwrite'            => false,
        'randomize'            => false,
    ];
    
    /**
     * @var Request
     */
    protected $request;
    
    /**
     * @param $request
     */
    public function setRequest($request)
    {
    	$this->request = $request;
    }
    
  
    /**
     * Override moveUploadedFile
     *
     * If the request is not HTTP, or not a PUT or PATCH request, delegates to
     * the parent functionality.
     *
     * Otherwise, does a `rename()` operation, and returns the status of the
     * operation.
     *
     * @param string $sourceFile
     * @param string $targetFile
     * @return bool
     * @throws FilterRuntimeException in the event of a warning
     */
    protected function moveUploadedFile($sourceFile, $targetFile)
    { 	
    	if ($this->request=='POST')
    	{
    		return parent::moveUploadedFile($sourceFile, $targetFile);
    	}
	elseif ($this->request=='PUT' || $this->request=='PATCH')
	{
    		ErrorHandler::start();
    			
    		$current = file_get_contents($sourceFile);
    		$result = file_put_contents($targetFile, $current);
    			     
    		$warningException = ErrorHandler::stop();
    
    		if (false === $result || null !== $warningException) 
		{
    			throw new FilterRuntimeException(
    			sprintf('File "%s" could not be renamed. An error occurred while processing the file.', $sourceFile),0,$warningException);
    		}

    		return $result;
	}
    }

    /**
     * @param S3Client $client
     * @param array    $options
     */
    public function __construct(S3Client $client, $options = [])
    {
        parent::__construct($options);

        // We need to register the S3 stream wrapper so that we can take advantage of the base class
        $this->client = $client;
        $this->client->registerStreamWrapper();
    }

    /**
     * Set the bucket name
     *
     * @param  string $bucket
     *
     * @return S3RenameUpload
     */
    public function setBucket($bucket)
    {
        $this->options['bucket'] = trim($bucket, '/');
        return $this;
    }

    /**
     * Get the bucket name
     *
     * @return string
     */
    public function getBucket()
    {
        return $this->options['bucket'];
    }

    /**
     * This method is overloaded so that the final target points to a URI using S3 protocol
     *
     * {@inheritdoc}
     */
    protected function getFinalTarget($uploadData)
    {
        // We cannot upload without a bucket
        if (null === $this->options['bucket']) {
            throw new MissingBucketException('No bucket was set when trying to upload a file to S3');
        }

        // Get the tmp file name and convert it to an S3 key
        $key = trim(str_replace('\\', '/', parent::getFinalTarget($uploadData)), '/');
        if (strpos($key, './') === 0) {
            $key = substr($key, 2);
        }

        return "s3://{$this->options['bucket']}/{$key}";
    }
}
