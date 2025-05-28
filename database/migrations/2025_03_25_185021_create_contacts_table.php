<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Executa as migrações.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table) {
            // Chave primária do tipo UUID
            $table->uuid('id')->primary();

            // Campos gerais do contato
            $table->string('type'); // Define o tipo de contato: físico ou jurídico
            $table->string('name'); // Nome ou Razão Social
            $table->string('cpf_cnpj')->unique()->nullable(); // CPF ou CNPJ, único e opcional

            // Campos de endereço
            $table->string('zip_code')->nullable(); // CEP
            $table->string('address')->nullable(); // Logradouro
            $table->string('number')->nullable(); // Número
            $table->string('complement')->nullable(); // Complemento
            $table->string('neighborhood')->nullable(); // Bairro
            $table->string('city')->nullable(); // Cidade
            $table->string('state')->nullable(); // Estado/UF
            $table->string('country')->nullable(); // País

            // Campos Específicos para Pessoa Física
            $table->string('rg')->nullable(); // RG
            $table->string('gender')->nullable(); // Gênero
            $table->string('nationality')->nullable(); // Nacionalidade
            $table->string('marital_status')->nullable(); // Estado Civil
            $table->string('profession')->nullable(); // Profissão
            $table->date('date_of_birth')->nullable(); // Data de Nascimento

            // Campos Específicos para Pessoa Jurídica
            $table->string('business_name')->nullable(); // Razão Social (pode ser o mesmo que 'name' ou diferente)
            $table->text('business_activity')->nullable(); // Atividade Empresarial/Objeto Social
            $table->string('tax_state')->nullable(); // Inscrição Estadual
            $table->string('tax_city')->nullable(); // Inscrição Municipal

            // --- INÍCIO DA CORREÇÃO DA CHAVE ESTRANGEIRA ---
            // Coluna para o ID do administrador (que também é um contato)
            // Deve ser do tipo UUID para corresponder à coluna 'id' e permitir nulos
            $table->uuid('administrator_id')->nullable();
            // --- FIM DA CORREÇÃO DA CHAVE ESTRANGEIRA ---

            // Timestamps padrão do Laravel (created_at e updated_at)
            $table->timestamps();

            // --- DEFINIÇÃO DA RESTRIÇÃO DE CHAVE ESTRANGEIRA ---
            // A restrição é definida após a criação da coluna
            // Referencia a coluna 'id' na mesma tabela 'contacts'
            // onDelete('set null') significa que se o contato administrador for excluído,
            // o campo 'administrator_id' nos contatos associados será definido como NULL.
            $table->foreign('administrator_id')
                  ->references('id')
                  ->on('contacts')
                  ->onDelete('set null');
            // --- FIM DA DEFINIÇÃO DA RESTRIÇÃO ---
        });
    }

    /**
     * Reverte as migrações.
     *
     * @return void
     */
    public function down(): void
    {
        // Remove a tabela 'contacts' se ela existir
        Schema::dropIfExists('contacts');
    }
};
