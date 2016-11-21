<?php //strict

namespace LayoutCore\Services;

use LayoutCore\Models\LocalizedOrder;
use Plenty\Modules\Account\Contact\Contracts\ContactRepositoryContract;
use Plenty\Modules\Account\Contact\Contracts\ContactAddressRepositoryContract;
use Plenty\Modules\Account\Address\Contracts\AddressRepositoryContract;
use Plenty\Modules\Account\Contact\Models\Contact;
use LayoutCore\Builder\Order\AddressType;
use Plenty\Modules\Account\Address\Models\Address;
use Plenty\Plugin\Application;
use LayoutCore\Helper\AbstractFactory;
use LayoutCore\Helper\UserSession;
use Plenty\Modules\Order\Contracts\OrderRepositoryContract;
use Plenty\Modules\Order\Models\Order;
use LayoutCore\Services\AuthenticationService;
use LayoutCore\Services\SessionStorageService;
use LayoutCore\Constants\SessionStorageKeys;

/**
 * Class CustomerService
 * @package LayoutCore\Services
 */
class CustomerService
{
	/**
	 * @var ContactRepositoryContract
	 */
	private $contactRepository;
	/**
	 * @var ContactAddressRepositoryContract
	 */
	private $contactAddressRepository;
    /**
     * @var AddressRepositoryContract
     */
    private $addressRepository;
	/**
	 * @var OrderRepositoryContract
	 */
	private $orderRepository;
	/**
	 * @var AuthenticationService
	 */
	private $authService;
    /**
     * @var SessionStorageService
     */
    private $sessionStorage;
	/**
	 * @var UserSession
	 */
	private $userSession = null;
	/**
	 * @var AbstractFactory
	 */
	private $factory;
    
    /**
     * CustomerService constructor.
     * @param ContactRepositoryContract $contactRepository
     * @param ContactAddressRepositoryContract $contactAddressRepository
     * @param AddressRepositoryContract $addressRepository
     * @param OrderRepositoryContract $orderRepository
     * @param \LayoutCore\Services\AuthenticationService $authService
     * @param AbstractFactory $factory
     */
	public function __construct(
		ContactRepositoryContract $contactRepository,
		ContactAddressRepositoryContract $contactAddressRepository,
        AddressRepositoryContract $addressRepository,
		OrderRepositoryContract $orderRepository,
		AuthenticationService $authService,
        SessionStorageService $sessionStorage,
		AbstractFactory $factory)
	{
		$this->contactRepository        = $contactRepository;
		$this->contactAddressRepository = $contactAddressRepository;
        $this->addressRepository        = $addressRepository;
		$this->orderRepository          = $orderRepository;
		$this->authService              = $authService;
        $this->sessionStorage           = $sessionStorage;
		$this->factory                  = $factory;
	}

    /**
     * Get the ID of the current contact from the session
     * @return int
     */
	public function getContactId():int
	{
		if($this->userSession === null)
		{
			$this->userSession = $this->factory->make(UserSession::class);
		}
		return $this->userSession->getCurrentContactId();
	}

    /**
     * Create a contact with addresses if specified
     * @param array $contactData
     * @param null $billingAddressData
     * @param null $deliveryAddressData
     * @return Contact
     */
	public function registerCustomer(array $contactData, $billingAddressData = null, $deliveryAddressData = null):Contact
	{
		$contact = $this->createContact($contactData);

		if($contact->id > 0)
		{
			//Login
			$this->authService->loginWithContactId($contact->id, (string)$contactData['password']);
		}

		if($billingAddressData !== null)
		{
			$this->createAddress($billingAddressData, AddressType::BILLING);
			if($deliveryAddressData === null)
			{
				$this->createAddress($billingAddressData, AddressType::DELIVERY);
			}
		}

		if($deliveryAddressData !== null)
		{
			$this->createAddress($deliveryAddressData, AddressType::DELIVERY);
		}

		return $contact;
	}

    /**
     * Create a new contact
     * @param array $contactData
     * @return Contact
     */
	public function createContact(array $contactData):Contact
	{
		$contact = $this->contactRepository->createContact($contactData);
		return $contact;
	}

    /**
     * Find the current contact by ID
     * @return null|Contact
     */
	public function getContact()
	{
		if($this->getContactId() > 0)
		{
			return $this->contactRepository->findContactById($this->getContactId());
		}
		return null;
	}

    /**
     * Update a contact
     * @param array $contactData
     * @return null|Contact
     */
	public function updateContact(array $contactData)
	{
		if($this->getContactId() > 0)
		{
			return $this->contactRepository->updateContact($contactData, $this->getContactId());
		}

		return null;
	}

    /**
     * List the addresses of a contact
     * @param null $type
     * @return array|\Illuminate\Database\Eloquent\Collection
     */
	public function getAddresses($type = null)
	{
        if($this->getContactId() > 0)
        {
            return $this->contactAddressRepository->getAddresses($this->getContactId(), $type);
        }
        else
        {
            $address = null;
            
            if($type == AddressType::BILLING && $this->sessionStorage->getSessionValue(SessionStorageKeys::BILLING_ADDRESS_ID) > 0)
            {
                $address = $this->addressRepository->findAddressById($this->sessionStorage->getSessionValue(SessionStorageKeys::BILLING_ADDRESS_ID));
            }
            elseif($type == AddressType::DELIVERY && $this->sessionStorage->getSessionValue(SessionStorageKeys::DELIVERY_ADDRESS_ID) > 0)
            {
                $address = $this->addressRepository->findAddressById($this->sessionStorage->getSessionValue(SessionStorageKeys::DELIVERY_ADDRESS_ID));
            }
    
            if($address instanceof Address)
            {
                return [
                    $address
                ];
            }
    
            return [];
        }
	}

    /**
     * Get an address by ID
     * @param int $addressId
     * @param int $type
     * @return Address
     */
	public function getAddress(int $addressId, int $type):Address
	{
        if($this->getContactId() > 0)
        {
            return $this->contactAddressRepository->getAddress($addressId, $this->getContactId(), $type);
        }
        else
        {
            if($type == AddressType::BILLING)
            {
                return $this->addressRepository->findAddressById($this->sessionStorage->getSessionValue(SessionStorageKeys::BILLING_ADDRESS_ID));
            }
            elseif($type == AddressType::DELIVERY)
            {
                return $this->addressRepository->findAddressById($this->sessionStorage->getSessionValue(SessionStorageKeys::DELIVERY_ADDRESS_ID));
            }
        }
	}

    /**
     * Create an address with the specified address type
     * @param array $addressData
     * @param int $type
     * @return Address
     */
	public function createAddress(array $addressData, int $type):Address
	{
        if($this->getContactId() > 0)
        {
            return $this->contactAddressRepository->createAddress($addressData, $this->getContactId(), $type);
        }
		else
        {
            return $this->createGuestAddress($addressData, $type);
        }
	}
    
    /**
     * @param array $addressData
     * @return Address
     */
	private function createGuestAddress(array $addressData, int $type):Address
    {
        $newAddress = $this->addressRepository->createAddress($addressData);
    
        if($type == AddressType::BILLING)
        {
            $this->sessionStorage->setSessionValue(SessionStorageKeys::BILLING_ADDRESS_ID, $newAddress->id);
        }
        elseif($type == AddressType::DELIVERY)
        {
            $this->sessionStorage->setSessionValue(SessionStorageKeys::DELIVERY_ADDRESS_ID, $newAddress->id);
        }
        
        return $newAddress;
    }

    /**
     * Update an address
     * @param int $addressId
     * @param array $addressData
     * @param int $type
     * @return Address
     */
	public function updateAddress(int $addressId, array $addressData, int $type):Address
	{
		return $this->contactAddressRepository->updateAddress($addressData, $addressId, $this->getContactId(), $type);
	}

    /**
     * Delete an address
     * @param int $addressId
     * @param int $type
     */
	public function deleteAddress(int $addressId, int $type)
	{
        if($this->getContactId() > 0)
        {
            $this->contactAddressRepository->deleteAddress($addressId, $this->getContactId(), $type);
        }
        else
        {
            $this->addressRepository->deleteAddress($addressId);
        }
	}

    /**
     * Get a list of orders for the current contact
     * @param int $page
     * @param int $items
     * @return array|\Plenty\Repositories\Models\PaginatedResult
     */
	public function getOrders(int $page = 1, int $items = 10)
	{
		return AbstractFactory::create(\LayoutCore\Services\OrderService::class)->getOrdersForContact(
		    $this->getContactId(),
            $page,
            $items
        );
	}

    /**
     * Get the last order created by the current contact
     * @return LocalizedOrder
     */
	public function getLatestOrder():LocalizedOrder
	{
        return AbstractFactory::create(\LayoutCore\Services\OrderService::class)->getLatestOrderForContact(
            $this->getContactId()
        );
	}
}
