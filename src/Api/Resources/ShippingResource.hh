<?hh //strict

namespace LayoutCore\Api\Resources;

use Illuminate\Http\Response;
use Plenty\Plugin\Http\Request;
use LayoutCore\Api\ApiResource;
use LayoutCore\Api\ApiResponse;
use LayoutCore\Api\ResponseCode;
use LayoutCore\Services\ShippingService;

class ShippingResource extends ApiResource
{

    private ShippingService $shippingService;

    public function __construct( Request $request, ApiResponse $response, ShippingService $shippingService )
    {
        parent::__construct( $request, $response );
        $this->shippingService = $shippingService;
    }

    // put/patch
    public function update( string $shippingProfileId ):Response
    {
        $this->shippingService->setShippingProfileId( (int) $shippingProfileId );
        return $this->response->create( ResponseCode::OK );
    }

}
