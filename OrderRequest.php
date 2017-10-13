<?php

namespace App\Http\Requests;

use App\Http\Requests\Request;

class OrderRequest extends \Backpack\CRUD\app\Http\Requests\CrudRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'name' => 'required||max:255',
            'surname' => 'required||max:255',
            'email' => 'required|email',
            'phone' => 'required|regex:/^\+?\d ?\(?\d{3}\)? ?\d{3}-?\d{4,5}$/|min:5|max:25',
            'comment' => 'max:500',
            'admin_comments' => 'max:500',
            'delivery' => 'integer',
            'payment' => 'integer'
        ];
    }

    /**
     * Get the validation attributes that apply to the request.
     *
     * @return array
     */
    public function attributes()
    {
        return [
            //
        ];
    }

    /**
     * Get the validation messages that apply to the request.
     *
     * @return array
     */
    public function messages()
    {
        return [
            'name.required' => 'Введите имя',
            'surname.required' => 'Введите фамилию',
            'email.required' => 'Введите правильный email',
            'phone.required' => 'Введите номер телефона'
        ];
    }
}