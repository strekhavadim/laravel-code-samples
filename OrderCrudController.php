<?php namespace App\Http\Controllers\Admin;

use Backpack\CRUD\app\Http\Controllers\CrudController;
use App\Http\Requests\OrderRequest as StoreRequest;
use App\Http\Requests\OrderRequest as UpdateRequest;

class OrderCrudController extends CrudController {

	public function setup() 
    {
        $user = \Auth::user();

        /*
		|--------------------------------------------------------------------------
		| BASIC CRUD INFORMATION
		|--------------------------------------------------------------------------
		*/
        $this->crud->setModel("App\Models\Order");
        $this->crud->setRoute("birzha_manager/order");
        $this->crud->setEntityNameStrings('заказ', 'заказы');

        $this->crud->addColumn([
            'name' => 'id',
            'label' => 'Номер',
        ]);

        $this->crud->addColumn([
            'label' => 'Статус',
            'type' => 'status',
            'name' => 'status_id',
            'entity' => 'status',
            'attribute' => 'title',
            'model' => "App\Models\Status",
        ]);

        $this->crud->addColumn([
            'label' => 'Событие',
            'type' => "model_function",
            'function_name' => 'getEventsTitles',
        ]);

        $this->crud->addColumn([
            'label' => "Тип оплаты",
            'type' => 'select',
            'name' => 'payment_type_id',
            'entity' => 'paymentType',
            'attribute' => 'name',
            'model' => "App\Models\PaymentType"
        ]);


        $this->crud->addColumn([
            'name' => 'created_at',
            'label' => 'Дата заказа',
            'type' => 'datetime'
        ]);

        $this->crud->addColumn([
            'name' => 'cost',
            'label' => 'Цена',
        ]);

        $this->crud->addField([
            'name' => 'name',
            'label' => 'Имя клиента',
            'type' => 'text',
            'disabled' => 'disabled'
        ]);

        $this->crud->addField([
            'name' => 'surname',
            'label' => 'Фамилия клиента',
            'type' => 'text',
            'disabled' => 'disabled'
        ]);

        $this->crud->addField([
            'name' => 'email',
            'label' => 'Email клиента',
            'type' => 'email',
            'disabled' => 'disabled'
        ]);

        $this->crud->addField([
            'name' => 'phone',
            'label' => 'Телефон клиента',
            'type' => 'text',
            'disabled' => 'disabled'
        ]);

        $this->crud->addField([
            'label' => "Статус",
           'type' => 'select2',
           'name' => 'status_id',
           'entity' => 'status',
           'attribute' => 'title',
           'model' => "App\Models\Status"
        ]);

        $this->crud->addField([
            'name' => 'tickets',
            'type' => 'tickets',
            'label' => 'Билеты',
            'entity' => 'tickets',
            'model' => "App\Models\Ticket"
        ]);

       $this->crud->addField([
            'name' => 'cost',
            'label' => 'Цена заказа',
            'type' => 'number',
        ]);

        $this->crud->addField([
            'name' => 'comments',
            'label' => 'Коментарии к заказу',
            'type' => 'ckeditor',
            'placeholder' => 'Ваш текст тут',
            'disabled' => 'disabled'
        ]);

        $this->crud->addField([
            'name' => 'admin_comments',
            'label' => 'Коментарии администратора',
            'type' => 'ckeditor',
            'placeholder' => 'Ваш текст тут',
        ]);

        $this->crud->addField([
            'label' => "Тип доставки",
            'type' => 'select2',
            'name' => 'delivery_id'
            'entity' => 'delivery',
            'attribute' => 'name',
            'model' => "App\Models\Delivery"
        ]);

        $this->crud->addField([
            'label' => "Тип оплаты",
            'type' => 'select2',
            'name' => 'payment_type_id',
            'entity' => 'paymentType',
            'attribute' => 'name',
            'model' => "App\Models\PaymentType"
        ]);
		

        // ------ CRUD ACCESS
        if ($user->hasRole('Admin')) {
            $this->crud->denyAccess(['create']);
        } elseif ($user->hasRole('Client manager')) {
            $this->crud->denyAccess(['create', 'delete']);
        } else {
            $this->crud->denyAccess(['list', 'create', 'update', 'reorder', 'delete']);
        }

	    $this->crud->orderBy('created_at', 'desc');
    }

	public function store(StoreRequest $request)
	{
		return parent::storeCrud();
	}

	public function update(UpdateRequest $request)
	{
		return parent::updateCrud();
	}
}
