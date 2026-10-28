<?php

namespace Tests\Unit;

use App\Support\Normalizer;
use PHPUnit\Framework\TestCase;

class NormalizerTest extends TestCase
{
    public function test_email_is_trimmed_and_lowercased(): void
    {
        $this->assertSame('jean.dupont@exemple.fr', Normalizer::email('  Jean.Dupont@Exemple.FR '));
        $this->assertNull(Normalizer::email('   '));
    }

    public function test_company_name_ignores_legal_form_accents_and_punctuation(): void
    {
        $this->assertSame(Normalizer::companyName('Agence Dupont SARL'), Normalizer::companyName('agence  DUPONT, s.a.r.l'));
        $this->assertSame('cafe des arts', Normalizer::companyName('Café des Arts SAS'));
    }

    public function test_registration_number_accepts_siren_and_siret_only(): void
    {
        $this->assertSame('552100554', Normalizer::registrationNumber('552 100 554'));
        $this->assertSame('55210055400013', Normalizer::registrationNumber('552 100 554 00013'));
        $this->assertSame('552100554', Normalizer::siren('55210055400013'));
        $this->assertNull(Normalizer::registrationNumber('12345'));
    }

    public function test_french_phone_numbers_are_converted_to_international_format(): void
    {
        $this->assertSame('+33612345678', Normalizer::phone('06 12 34 56 78'));
        $this->assertSame('+33612345678', Normalizer::phone('0033 6 12 34 56 78'));
    }

    public function test_domain_is_extracted_from_website(): void
    {
        $this->assertSame('exemple.fr', Normalizer::domain('https://www.Exemple.fr/contact'));
        $this->assertSame('exemple.fr', Normalizer::domain('exemple.fr'));
        $this->assertNull(Normalizer::domain(''));
    }
}
