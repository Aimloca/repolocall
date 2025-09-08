<?

class MyData {

	public static function Mark($document) {

		// Get company
		if(empty($document->company)) $document->GetData();
		// Get company parameters
		$company_parameters=$document->company->GetParameters();

		// Get series
		if(empty($document->series)) $document->GetData();

		// Get customer
		if(empty($document->customer)) $document->GetData();

		// Get products
		if(empty($document->products)) $document->GetData();

		// Get VAT categories data
		$vat_category_codes=[];
		$sql="SELECT percent, vat_category_mydata_code FROM VAT_CATEGORY;";
		if($rows=DB::Query($sql)) foreach($rows as $row) $vat_category_codes[$row['percent']]=$row['vat_category_mydata_code'];

		// Get income categories data
		$vat_incode_category_codes=[];
		$sql="SELECT id, code FROM MYDATA_INCOME_CATEGORY;";
		if($rows=DB::Query($sql)) foreach($rows as $row) $vat_incode_category_codes[$row['id']]=$row['code'];

		// Get income types data
		$vat_incode_type_codes=[];
		$sql="SELECT id, code FROM MYDATA_INCOME_TYPE;";
		if($rows=DB::Query($sql)) foreach($rows as $row) $vat_incode_type_codes[$row['id']]=$row['code'];

		// Initialize variables
		$products_xml='';
		$summary_xml='';
		$mydata_xml='';
		$total_amount=0;
		$total_net_amount=0;
		$total_vat_amount=0;
		$amount_tax_cat1_1=0;
		$amount_tax_cat1_2=0;
		$issue_date=date('Y-m-d', strtotime($document->date));

		// Loop through products
		foreach($document->products as $product_index=>$product) {
			// Increase totals
			$total_amount+=$product->amount;
			$total_net_amount+=$product->net_amount;
			$total_vat_amount+=$product->vat_amount;
			$amount_tax_cat1_1+=$vat_incode_category_codes[$product->mydata_income_category]=='category1_1' ? $product->net_amount : 0;
			$amount_tax_cat1_2+=$vat_incode_category_codes[$product->mydata_income_category]=='category1_2' ? $product->net_amount : 0;
			// Add to xml
			$products_xml.=MyData::PrepareProductXML($product_index + 1, $vat_category_codes[$product->vat_percent], $vat_incode_category_codes[$product->mydata_income_category], $vat_incode_type_codes[$product->mydata_income_type], $product->net_amount, $product->vat_amount);
		}

		// Check document series
		if($document->series->retail) { // Retail

			// Get my_data xml
			$mydata_xml=MyData::PrepareRetailXML($document->company->tax_number, $document->company->country_iso_code, $document->series->code, $document->series->sequence, $issue_date, $total_amount, $total_net_amount, $total_vat_amount);

			// Add items in my_data xml
			$mydata_xml=str_replace('#items#', $products_xml, $mydata_xml);

			// Add summary xml
			if($amount_tax_cat1_1>0)
				$summary_xml.=MyData::PrepareRetailSummaryXML($amount_tax_cat1_1, 'category1_1');
			else if($amount_tax_cat1_2>0)
				$summary_xml.=MyData::PrepareRetailSummaryXML($amount_tax_cat1_2, 'category1_2');

			// Add summary in my_data xml
			$mydata_xml=str_replace('#summary#', $summary_xml, $mydata_xml);

		} else {

			// Get my_data xml
			$mydata_xml=MyData::PrepareInvoiceXML($document->company->tax_number, $document->company->country_iso_code, $document->customer->tax_number , $document->series->code, $document->series->sequence, $issue_date, $total_amount, $total_net_amount, $total_vat_amount);

			// Add items in my_data xml
			$mydata_xml=str_replace('#items#', $products_xml, $mydata_xml);

			// Add summary xml
			if($amount_tax_cat1_1>0)
				$summary_xml.=MyData::PrepareInvoiceSummaryXML($amount_tax_cat1_1, 'category1_1');
			else if($amount_tax_cat1_2>0)
				$summary_xml.=MyData::PrepareInvoiceSummaryXML($amount_tax_cat1_2, 'category1_2');

			// Add summary in my_data xml
			$mydata_xml=str_replace('#summary#', $summary_xml, $mydata_xml);
		}

		// Get connection details
		$mydata_username=$company_parameters->mydata_debug ? $company_parameters->mydata_username_debug : $company_parameters->mydata_username;
		$mydata_api_key=$company_parameters->mydata_debug ? $company_parameters->mydata_api_key_debug : $company_parameters->mydata_api_key;
		$url=$company_parameters->mydata_debug ? 'https://mydataapidev.aade.gr/SendInvoices' : 'https://mydatapi.aade.gr/myDATA/SendInvoices';

		return MyData::ApiRequest($mydata_username, $mydata_api_key, $url, $mydata_xml, $document->id);

	}

	private static function PrepareProductXML($product_index, $vat_category_code, $income_category_code, $income_type_code, $net_amount, $vat_amount){
		return "
			<invoiceDetails>
				<lineNumber>{$product_index}</lineNumber>
				<netValue>{$net_amount}</netValue>
				<vatCategory>{$vat_category_code}</vatCategory>
				<vatAmount>{$vat_amount}</vatAmount>
				<incomeClassification>
					<classificationType xmlns=\"https://www.aade.gr/myDATA/incomeClassificaton/v1.0\">{$income_type_code}</classificationType>
					<classificationCategory xmlns=\"https://www.aade.gr/myDATA/incomeClassificaton/v1.0\">{$income_category_code}</classificationCategory>
					<amount xmlns=\"https://www.aade.gr/myDATA/incomeClassificaton/v1.0\">{$net_amount}</amount>
				</incomeClassification>
			</invoiceDetails>
		";
	}

	private static function prepareRetailXML($tax_number, $country_code, $series, $seq, $issue_date, $amount, $netvalue, $vatamount) {
		return "
			<InvoicesDoc xmlns:xsd=\"http://www.w3.org/2001/XMLSchema\" xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\" xmlns=\"http://www.aade.gr/myDATA/invoice/v1.0\">
				<invoice>
					<issuer>
						<vatNumber>{$tax_number}</vatNumber>
						<country>{$country_code}</country>
						<branch>0</branch>
					</issuer>
					<invoiceHeader>
						<series>{$series}</series>
						<aa>{$seq}</aa>
						<issueDate>{$issue_date}</issueDate>
						<invoiceType>11.1</invoiceType>
						<currency>EUR</currency>
					</invoiceHeader>
					<paymentMethods>
						<paymentMethodDetails>
							<type>5</type>
							<amount>" . number_format((float) $amount, 2) . "</amount>
						</paymentMethodDetails>
					</paymentMethods>

					#items#

					<invoiceSummary>
						<totalNetValue>" . number_format((float) $netvalue, 2) . "</totalNetValue>
						<totalVatAmount>" . number_format((float) $vatamount, 2) . "</totalVatAmount>
						<totalWithheldAmount>0</totalWithheldAmount>
						<totalFeesAmount>0</totalFeesAmount>
						<totalStampDutyAmount>0</totalStampDutyAmount>
						<totalOtherTaxesAmount>0</totalOtherTaxesAmount>
						<totalDeductionsAmount>0</totalDeductionsAmount>
						<totalGrossValue>" . number_format((float) $amount, 2) . "</totalGrossValue>

						#summary#

					</invoiceSummary>
				</invoice>
			</InvoicesDoc>
		";
	}

	private static function PrepareRetailSummaryXML($amount_tax_category, $category_title){
		return "
			<incomeClassification>
				<classificationType xmlns=\"https://www.aade.gr/myDATA/incomeClassificaton/v1.0\">E3_561_003</classificationType>
				<classificationCategory xmlns=\"https://www.aade.gr/myDATA/incomeClassificaton/v1.0\">{$category_title}</classificationCategory>
				<amount xmlns=\"https://www.aade.gr/myDATA/incomeClassificaton/v1.0\">" . number_format((float) $amount_tax_category, 2) . "</amount>
			</incomeClassification>
		";
	}


	private static function PrepareInvoiceSummaryXML($amount_tax_category, $category_title){
		return "
			<incomeClassification>
				<classificationType xmlns=\"https://www.aade.gr/myDATA/incomeClassificaton/v1.0\">E3_561_001</classificationType>
				<classificationCategory xmlns=\"https://www.aade.gr/myDATA/incomeClassificaton/v1.0\">{$category_title}</classificationCategory>
				<amount xmlns=\"https://www.aade.gr/myDATA/incomeClassificaton/v1.0\">" . number_format((float) $amount_tax_category, 2) . "</amount>
			</incomeClassification>
		";
	}

	private static function PrepareInvoiceXML($tax_number, $country_code, $customer_tax_number, $series, $seq, $issue_date, $amount, $netvalue, $vatamount) {
		return "
			<InvoicesDoc xmlns=\"http://www.aade.gr/myDATA/invoice/v1.0\" xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\" xmlns:icls=\"https://www.aade.gr/myDATA/incomeClassificaton/v1.0\" xmlns:ecls=\"https://www.aade.gr/myDATA/expensesClassificaton/v1.0\" xsi:schemaLocation=\"http://www.aade.gr/myDATA/invoice/v1.0/InvoicesDoc-v0.6.xsd\">
				<invoice>
					<issuer>
						<vatNumber>{$tax_number}</vatNumber>
						<country>{$country_code}</country>
						<branch>0</branch>
					</issuer>
					<counterpart>
						<vatNumber>{$customer_tax_number}</vatNumber>
						<country>GR</country>
						<branch>0</branch>
						<address>
						<postalCode></postalCode>
						<city></city>
						</address>
					</counterpart>
					<invoiceHeader>
						<series>{$series}</series>
						<aa>{$seq}</aa>
						<issueDate>{$issue_date}</issueDate>
						<invoiceType>1.1</invoiceType>
						<currency>EUR</currency>
					</invoiceHeader>
					<paymentMethods>
						<paymentMethodDetails>
							<type>3</type>
							<amount>" . number_format((float) $amount, 2) . "</amount>
						</paymentMethodDetails>
					</paymentMethods>

					#items#

					<invoiceSummary>
						<totalNetValue>" . number_format((float) $netvalue, 2) . "</totalNetValue>
						<totalVatAmount>" . number_format((float) $vatamount, 2) . "</totalVatAmount>
						<totalWithheldAmount>0.00</totalWithheldAmount>
						<totalFeesAmount>0.00</totalFeesAmount>
						<totalStampDutyAmount>0.00</totalStampDutyAmount>
						<totalOtherTaxesAmount>0.00</totalOtherTaxesAmount>
						<totalDeductionsAmount>0.00</totalDeductionsAmount>
						<totalGrossValue>" . number_format((float) $amount, 2) . "</totalGrossValue>

						#summary#

					</invoiceSummary>
				</invoice>
			</InvoicesDoc>
		";
	}

	private static function ApiRequest($mydata_username, $mydata_api_key, $url, $mydata_xml, $sale_doc_id) {

		$curl=curl_init();
		curl_setopt_array($curl, [
			CURLOPT_URL 				=> $url,
			CURLOPT_RETURNTRANSFER 		=> true,
			CURLOPT_ENCODING 			=> '',
			CURLOPT_MAXREDIRS 			=> 10,
			CURLOPT_TIMEOUT 			=> 0,
			CURLOPT_FOLLOWLOCATION 		=> true,
			CURLOPT_HTTP_VERSION 		=> CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST 		=> 'POST',
			CURLOPT_POSTFIELDS 			=> $mydata_xml,
			CURLOPT_HTTPHEADER 			=> [
											"aade-user-id:{$mydata_username}",
											"Ocp-Apim-Subscription-Key:{$mydata_api_key}",
											"Content-Type: application/xml",
										   ]
		]);

		try {
			$response=curl_exec($curl);
			if(strpos($response,"<invoiceMark>")){
				$start=strpos($response,"<invoiceMark>") + 13;
				$stop=strpos($response,"</invoiceMark>") - $start;
				$mark=substr($response, $start, $stop);

				$startqr=strpos($response,"<qrUrl>") + 7;
				$stopqr=strpos($response,"</qrUrl>") - $startqr;
				$qr_url=substr($response, $startqr, $stopqr);
				$sql='
					INSERT INTO MYDATA_LOGS (sale_document_id, inputXML, response, status) VALUES (
						' . DB::Quote($sale_doc_id) . ',
						' . DB::Quote($mydata_xml) . ',
						' . DB::Quote($response) . ',
						0
					);
				';
				DB::Query($sql);
				return new Response(true, 'OK', [ 'status' => 0, 'mark' => $mark, 'url' => $qr_url ]);
			} else {
				$sql='
					INSERT INTO MYDATA_LOGS (sale_document_id, inputXML, response, status) VALUES (
						' . DB::Quote($sale_doc_id) . ',
						' . DB::Quote($mydata_xml) . ',
						' . DB::Quote($response) . ',
						1
					);
				';
				DB::Query($sql);
				return new Response(false, Strings::Get('mydata_error_mark_not_found'), [ 'status' => 1, 'mark' => 1, 'url' => 1 ]);
			}
		} catch(Exception $ex) {
			$sql='
				INSERT INTO MYDATA_LOGS (sale_document_id, inputXML, response, status) VALUES (
					' . DB::Quote($sale_doc_id) . ',
					' . DB::Quote($mydata_xml) . ',
					' . DB::Quote($response) . ',
					-1
				);
			';
			DB::Query($sql);
			return new Response(false, Strings::Get('mydata_error_getting_mydata_mark') . ' ' . $ex->getMessage(), [ 'status' => -1, 'mark' => -1, 'url' => -1 ]);
		}
	}
}


