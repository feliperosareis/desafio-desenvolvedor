<?php

namespace App\Livewire;

use App\Models\TaxaPagamento;
use App\Models\TaxaValorCompra;
use Livewire\Attributes\Validate;
use Livewire\Component;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Http;

class Conversor extends Component
{
    #[Validate('required|numeric|min:1000|max:100000')] 
    public $valor = "";
    
    #[Validate('required')] 
    public $moeda = "";

    #[Validate('required')] 
    public $pagamento = "";

    public $resultado = "";
    public $moedas = [];

    public $taxaBoleto = 0;
    public $taxaCartao = 0;

    public $valorBaseCompra = 0;
    public $taxaValorMenor = 0;
    public $taxaValorMaior = 0;
    // public const TAXA_VALOR_ABAIXO = 0.02;
    // public const TAXA_VALOR_ACIMA = 0.01;


    public $taxaFormaPgto = 0;
    public $taxaValorConversao = 0;

    public $messages = [
        'valor.min' => 'O valor mínimo deve ser de R$ 1.000,00',
        'valor.max' => 'O valor máximo deve ser de R$ 100.000,00',
    ];

    public $operacao = [];
    protected $listeners = ['limparHistorico'];

    public function limparHistorico() {
        $this->operacao = [];
    }

    function converter() {

        $this->valor = $this->limpaValor($this->valor);

        $this->validate();

        $calculo = 0;
        if ($this->pagamento == 'Boleto') {
            $this->taxaFormaPgto = $this->taxaBoleto;
        } else {
            $this->taxaFormaPgto = $this->taxaCartao;            
        }
        
        if ($this->valor < $this->valorBaseCompra) {
            $this->taxaValorConversao = $this->taxaValorMenor;
        } else {
            $this->taxaValorConversao = $this->taxaValorMaior;
        }

        $valorMenosTaxas = $this->valor - ($this->valor * $this->taxaFormaPgto) - ($this->valor * $this->taxaValorConversao);
        $calculo = $this->formataValorToUS(($valorMenosTaxas / $this->getBid($this->moeda)));

        $this->resultado = $this->getCifrao($this->moeda) . ' ' . $calculo;

        $arr_operacoes = [
            'moeda' => $this->getCifrao($this->moeda),
            'valor' => $this->formataValorToBR($this->valor),
            'pagamento' => $this->pagamento,
            'valor_conversao' => $this->getBid($this->moeda),
            'valor_comprado' => $this->resultado,
            'taxa_pagamento' => $this->formataValorToBR($this->valor * $this->taxaFormaPgto),
            'taxa_conversao' => $this->formataValorToBR($this->valor * $this->taxaValorConversao),
            'valor_conversao_sem_taxa' => $this->formataValorToBR($this->valor - ($this->valor * $this->taxaFormaPgto) - ($this->valor * $this->taxaValorConversao))
        ];

        array_push($this->operacao, $arr_operacoes);

    }

    public function getTaxasPagamento() {
        $taxas = TaxaPagamento::all()->toArray();

        foreach ($taxas as $tx) {
            if ($tx['tipo_pagamento'] == 'Boleto') {
                $this->taxaBoleto = $tx['taxa'];
            } else {
                $this->taxaCartao = $tx['taxa'];
            }
        }
    }

    public function getTaxasValor() {
        $taxasValor = TaxaValorCompra::first();

        $this->valorBaseCompra = $taxasValor['valor_base'];
        $this->taxaValorMenor = $taxasValor['taxa_menor_valor'];
        $this->taxaValorMaior = $taxasValor['taxa_maior_valor'];
    }

    function listarMoedas() {
        $client = new Client();

        try {
            $response = $client->request('GET', 'https://economia.awesomeapi.com.br/json/last/USD-BRL,EUR-BRL,GBP-BRL,JPY-BRL,ARS-BRL');
    
            if ($response->getStatusCode() === 200) {
                $this->moedas = json_decode($response->getBody(), true);
            }
    
            $arr_moedas = collect(json_decode($response->getBody(), true));
            $this->moedas = $arr_moedas->map(function($arr) {
                $temp = explode('/', $arr['name']);
                return ['code' => $arr['code'], 'name' => $temp[0], 'bid' => $arr['bid']];
            });

        } catch (\Exception $e) {
            return response("Ocorreu um erro ao listar as moedas", 500);
        }
    }

    
    function getBid($string) {
        $bid = explode('|', $string)[1];
        return floatval($bid);
    }

    function getCifrao($string) {
        return $string = explode('|', $string)[0];
    }

    function limpaValor($valor) {
        return floatval(str_replace('.', '', $valor));
    }

    function formataValorToBR($valor) {
        return number_format($valor, 2, ',', '.');
    }

    function formataValorToUS($valor) {
        return number_format($valor, 2, '.', ',');
    }

    public function mount() {
        $this->moedas;
    }

    public function render()
    {
        $this->listarMoedas();
        $this->getTaxasPagamento();
        $this->getTaxasValor();
        return view('conversor', [
            'moedas' => $this->moedas,
            'resultado' => $this->resultado,
            'message' => $this->messages
        ])->layout('layouts.app');
    }
}
