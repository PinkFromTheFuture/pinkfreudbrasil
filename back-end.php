<?php

/**
 * Greetings! This is the CompanyTransferLotManager class!
 * may this code never ever need refactoring.
 */
class CompanyTransferLotManager extends CompanyManager
{
    const CLIENT_CODE = 'X12345Y';
    const LINE_BREAKS = "\r\n";

    public function getTransferLotById($transfer_lot_id, $for_update = false)
    {
        // check input.
        $transfer_lot_id = (int)$transfer_lot_id;
        if ($transfer_lot_id <= 0)
            throw new Exception("Invalid transfer_lot_id");

        // Begin query.
        $q = Doctrine_Query::create()->from('TransferLot')->where('id = ?',$transfer_lot_id);

        // For update.
        if ($for_update)
            $q->forUpdate(true);

        // get transfer lot.
        $transfer_lot = $q->execute()->getFirst();

        // Transfer lot obtained?
        if (!($transfer_lot instanceof TransferLot))
            throw new Exception(sprintf("Unable to get transfer lot '%d' from database.",$transfer_lot_id));

        // done.
        return $transfer_lot;
    }

    public function getTransferLotEntryById($transfer_lot_entry_id, $for_update = false)
    {
        // check input.
        $transfer_lot_entry_id = (int)$transfer_lot_entry_id;
        if ($transfer_lot_entry_id <= 0)
            throw new Exception("Invalid transfer_lot_entry_id");

        // Begin query.
        $q = Doctrine_Query::create()->from('TransferLotEntry')->where('id = ?',$transfer_lot_entry_id);

        // For update.
        if ($for_update)
            $q->forUpdate(true);

        // get transfer lot.
        $transfer_lot_entry = $q->execute()->getFirst();

        // Transfer lot entry obtained?
        if (!($transfer_lot_entry instanceof TransferLotEntry))
            throw new Exception(sprintf("Unable to get transfer lot entry '%d' from database.",$transfer_lot_entry_id));

        // done.
        return $transfer_lot_entry;
    }

    public function getTransferLotList($bring_related_values = false, $only_ok_status = false, $sort_ascending = true)
    {
        $transfer_lot_list = null;

        $transfer_lot_list_query = Doctrine_Query::create()->from('TransferLot tl');

        if ($bring_related_values)
        {
            $transfer_lot_list_query->innerJoin('tl.TransferLotType tlt');
            $transfer_lot_list_query->innerJoin('tl.TransferLotEntryStatus tls');
            $transfer_lot_list_query->innerJoin('tl.TransferLotProvider tlp');
        }
        if (!$only_ok_status)
            $transfer_lot_list_query->where('tl.status_id <> ?', TransferLotEntryStatus::OK);
        else
            $transfer_lot_list_query->where('tl.status_id = ?', TransferLotEntryStatus::OK);

        if ($sort_ascending)
            $transfer_lot_list_query->orderBy('id ASC');
        else
            $transfer_lot_list_query->orderBy('id DESC');

        $transfer_lot_list = $transfer_lot_list_query->execute();

        if ($transfer_lot_list->count() > 0 && !($transfer_lot_list[0] instanceof TransferLot))
            throw new Exception(sprintf("Invalid transfer lot."));

        return $transfer_lot_list;
    }

    public function getTransferLotEntriesByLot(TransferLot $transfer_lot)
    {
        // get transfer lot entries.
        return Doctrine_Query::create()
            ->from('TransferLotEntry tle')
            ->leftJoin('tle.DirectExternalAccount dea')
            ->leftJoin('dea.ExternalAccountTypeBank eatb')
            ->leftJoin('dea.OwnerUser ou')
            ->leftJoin('ou.Fdata f')
            ->leftJoin('f.FdataChunkTax t')
            ->leftJoin('tle.DirectTransferOutBuffer dtob')
            ->where('tle.transfer_lot_id = ?', $transfer_lot->id)
            ->execute();
    }

    private function _getExternalAccountsWithoutLotEntry($transfer_lot_provider_id = null, $for_update = false)
    {
        // Build query.
        $external_accounts_query = Doctrine_Query::create()
            ->from('ExternalAccount ea')
            ->innerJoin('ea.ExternalAccountTypeBank eatb')
            ->innerJoin('eatb.EnumBank eb')
            ->innerJoin('eb.TransferLotProviderToEnumBank prov')
            ->where('ea.transfer_lot_entry_id IS NULL')
            ->andWhere('ea.owner_type_id = ?', ExternalAccountOwnerType::USER)
            ->andWhere('ea.type_id = ?', ExternalAccountType::BANK)
            ->andWhere('ea.external_account_status_id = ?', ExternalAccountStatus::ENABLED)
            ->andWhere('prov.is_enabled = 1');

        // Transfer lot provider.
        if (!is_null($transfer_lot_provider_id))
            $external_accounts_query->andWhere('prov.transfer_lot_provider_id = ?',$transfer_lot_provider_id);

        // For update.
        if ($for_update)
            $external_accounts_query->forUpdate(true);

        // Get external accounts.
        return $external_accounts_query->execute();
    }

    private function _getTransferOutBufferEntriesWithoutLotEntry($transfer_lot_provider_id, $for_update = false)
    {
        // check input
        $transfer_lot_provider_id = (int) $transfer_lot_provider_id;
        if ($transfer_lot_provider_id <= 0)
            throw new Exception("Invalid transfer_lot_id");

        // get transfer out buffers.
        $tob_query = Doctrine_Query::create()
            ->from('TransferOutBuffer tob')
            ->innerJoin('tob.Transfer t') // this join is to ultimately get the is_enabled property from the provider to bank table.
            ->innerJoin('t.ExternalAccount ea') // this join is to ultimately get the is_enabled property from the provider to bank table.
            ->innerJoin('ea.TransferLotEntry eatle') // this join is meant to get only those that have CBUs loaded in the provider's system for at least one day and entries that aren't in a buffer yet.
            ->innerJoin('eatle.TransferLot eatl')
            ->where('tob.transfer_lot_entry_id IS null')
            ->andWhere('tob.is_cancelled = 0')
            ->andWhere('ea.preferred_provider_id is not null')
            ->andWhere('ea.preferred_provider_id = ?',$transfer_lot_provider_id)
        ;

        // Switch by provider.
        switch ($transfer_lot_provider_id)
        {
        case TransferLotProvider::INTERBANKING:
            // Debe pasar al menos 1 día desde que se aprobó el lot del external account.
            $tob_query->andWhere('eatl.status_id = ?',TransferLotEntryStatus::OK);
            $tob_query->andWhere('eatl.ok_at < ?', date("Y-m-d H:i:s", strtotime("-1 day")));
            // $tob_query->andWhere('(eatl.ok_at < ? OR ea.owner_id in (<<<<user_ids para overridear tiempo de 1d entre creación y upload de transfer>>>>))', date("Y-m-d H:i:s", strtotime("-1 day")));
            break;

        case TransferLotProvider::MANUAL:
            // 2014-08-29: No hay queries adicionales para filtrar los manuales.
            break;

        default:
            throw new Exception(sprintf("Unimplemented transfer_lot_provider_id: %s",$transfer_lot_provider_id));
        }

        // For update.
        if ($for_update)
            $tob_query->forUpdate(true);

        // done.
        return $tob_query->execute();
    }

    public function countExternalAccountsWithoutLotEntry()
    {
        return Doctrine_Query::create()
            ->from('ExternalAccount ea')
            ->innerJoin('ea.ExternalAccountTypeBank eatb')
            ->innerJoin('eatb.EnumBank eb')
            ->innerJoin('eb.TransferLotProviderToEnumBank prov')
            ->where('ea.transfer_lot_entry_id IS NULL')
            ->andWhere('ea.owner_type_id = ?', ExternalAccountOwnerType::USER)
            ->andWhere('ea.type_id = ?', ExternalAccountType::BANK)
            ->andWhere('ea.external_account_status_id = ?', ExternalAccountStatus::ENABLED)
            ->andWhere('prov.is_enabled = 1')
            ->count();
    }

    public function countTransferOutBufferEntriesWithoutLotEntry()
    {
        return Doctrine_Query::create()
            ->from('TransferOutBuffer tob')
            ->innerJoin('tob.Transfer t') // this join is to ultimately get the is_enabled property from the provider to bank table.
            ->innerJoin('t.ExternalAccount ea') // this join is to ultimately get the is_enabled property from the provider to bank table.
            ->innerJoin('ea.TransferLotEntry eatle') // this join is meant to get only those that have CBUs loaded in the provider's system for at least one day and entries that aren't in a buffer yet.
            ->innerJoin('eatle.TransferLot eatl')
            ->where('tob.transfer_lot_entry_id IS null')
            ->andWhere('tob.is_cancelled = 0')
            ->andWhere('ea.preferred_provider_id is not null') // if there is a problem, this might be the cause :p
            ->count();
    }

    public function createTransferOutBuffer(Transfer $transfer)
    {
        // Verifico que el operation esté pending.
        if ($transfer->InvoiceBill->Operation->operation_status_id != OperationStatus::PENDING)
            throw new Exception(sprintf("The operation '%s' is not in PENDING status.",$transfer->InvoiceBill->Operation->id));

        // Verifico que el external account esté en estado activo.
        if ($transfer->ExternalAccount->external_account_status_id != ExternalAccountStatus::ENABLED)
            throw new Exception(sprintf("The external_account '%s' is not in ACTIVE status.",$transfer->ExternalAccount->id));

        // Filter transfer_amount.
        $transfer_amount = CompanyContext::getInvoice()->verify2DigitsAmount($transfer->amount);
        if ($transfer_amount <= 0)
            throw new Exception(sprintf("Invalid transfer_amount: %s",$transfer_amount));

        // Create new transfer_out_buffer.
        $tob = new TransferOutBuffer();
        $tob->transfer_id = $transfer->id;
        $tob->transfer_lot_entry_id = null; // will be added after the instantiation of the transfer lot and transfer lot entry
        $tob->transfer_amount = $transfer_amount;
        $tob->save();

        // done.
        return $tob;
    }

    public function getTransferOutBufferByTransferLotEntry(TransferLotEntry $transfer_lot_entry, $for_update = false)
    {
        // Begin query.
        $q = Doctrine_Query::create()->from('TransferOutBuffer tob')
            ->where('transfer_lot_entry_id = ?', $transfer_lot_entry->id)
        ;

        // For update.
        if ($for_update)
            $q->forUpdate(true);

        // Get tob.
        $tob = $q->execute()->getFirst();
        if (!($tob instanceof TransferOutBuffer))
            throw new Exception(sprintf("Couldn't find a transfer out buffer with transfer_lot_entry_id = '%d'", $transfer_lot_entry->id));

        // done.
        return $tob;
    }

    public function formatTransferOutBufferAmountByProvider(TransferOutBuffer $transfer_out_buffer, $transfer_lot_provider_id)
    {
        // Filter provider.
        $transfer_lot_provider_id = (int)$transfer_lot_provider_id;
        if ($transfer_lot_provider_id <= 0)
            throw new Exception(sprintf("Invalid transfer_lot_provider_id"));

        // Get transfer_amount.
        $transfer_amount = CompanyContext::getInvoice()->verify2DigitsAmount($transfer_out_buffer->transfer_amount);

        // Switch by provider.
        switch ($transfer_lot_provider_id)
        {
        case TransferLotProvider::INTERBANKING:
            // Ejemplos de formato del importe para INTERBANKIN:
            // Si el importe de la transferencia es $ 100,00, se deberá ingresar el valor 00000000000010000
            // Si el importe de la transferencia es $ 150,25, se deberá ingresar el valor 00000000000015025
            // so we will return something like : 10000 or 15025, respectively
            $transfer_amount = number_format($transfer_amount, 2, '', '');
            break;

        case TransferLotProvider::MANUAL:
            $transfer_amount = number_format($transfer_amount, 2);
            break;

        default:
            throw new Exception(sprintf("Something went wrong when trying to format an amount. amount: = '%s', transfer_lot_provider id: '%d'", $transfer_out_buffer->transfer_amount, $transfer_lot_provider->id));
            break;
        }

        // done.
        return $transfer_amount;
    }

    public function moveTransferLotEntryByIds(array $transfer_lot_entry_ids, $new_provider_id)
    {
        try
        {
            // begin transaction.
            CompanyContext::getDb()->beginTransaction();

            // Select for update the target entry(ies)
            $entries_to_be_moved = Doctrine_Query::create()
                ->from('TransferLotEntry tle')
                ->innerJoin('tle.TransferLot tl')
                ->whereIn('id',$transfer_lot_entry_ids)
                ->forUpdate(true)
                ->execute();

            // We have found something?
            if ($entries_to_be_moved->count() <= 0)
                throw new Exception(sprintf("Unable to find entries to move."));

            // Camina por los entries para ver cuál es el provider actual.
            $actual_provider_id = null;
            $actual_type_id = null;
            foreach ($entries_to_be_moved as $entry_to_be_moved)
            {
                // Calculate actual_provider_id.
                if (is_null($actual_provider_id))
                    $actual_provider_id = $entry_to_be_moved->TransferLot->provider_id;
                elseif ($actual_provider_id != $entry_to_be_moved->TransferLot->provider_id)
                    throw new Exception(sprintf("The given group of entries (%s), has at least 2 providers inside it.",implode(',',$transfer_lot_entry_ids)));

                // Calculate actual_type_id.
                if (is_null($actual_type_id))
                    $actual_type_id = $entry_to_be_moved->TransferLot->type_id;
                elseif ($actual_type_id != $entry_to_be_moved->TransferLot->type_id)
                    throw new Exception(sprintf("The given group of entries (%s), has at least 2 types inside it.",implode(',',$transfer_lot_entry_ids)));

                // Me aseguro que el transfer_lot tenga status correcto.
                if ($entry_to_be_moved->TransferLot->status_id != TransferLotEntryStatus::PENDING)
                    throw new Exception(sprintf("The transfer_lot '%s' of entry '%s' does not have PENDING status.",$entry_to_be_moved->TransferLot->id,$entry_to_be_moved->id));
            }

            // Tengo actual_provider_id?
            if (is_null($actual_provider_id))
                throw new Exception(sprintf("Unknown actual_provider_id"));

            // Tengo actual_type_id?
            if (is_null($actual_type_id))
                throw new Exception(sprintf("Unknown actual_type_id"));

            // New provider and actual provider are equal?
            if ($actual_provider_id == $new_provider_id)
                throw new Exception(sprintf("The new provider '%s' is equal to actual provider.",$new_provider_id));

            // instantiate a new *manual* lot for these entries
            $new_transfer_lot = new TransferLot();
            $new_transfer_lot->type_id = $actual_type_id;
            $new_transfer_lot->status_id = TransferLotEntryStatus::PENDING;
            $new_transfer_lot->transfer_lot_provider_id = $new_provider_id;
            $new_transfer_lot->save();

            // insert those new entries we selected before into the new lot and then remove then from the old lot
            foreach ($entries_to_be_moved as $entry_to_be_moved)
            {
                $entry_to_be_moved->transfer_lot_id = $new_transfer_lot->id;
                $entry_to_be_moved->updated_at = date("Y-m-d H:i:s");
                $entry_to_be_moved->save();
            }

            // commit transaction.
            CompanyContext::getDb()->commit();

            // return the instantiated lot on success
            return $new_transfer_lot;
        }
        catch(Exception $e)
        {
            // rollback transaction.
            CompanyContext::getDb()->rollback();

            // re-throw same exception.
            throw $e;
        }
    }

    // decidí cancelar un TOB< entonces tengo que cancelarlo. por ejemplo, sacarlo del lote y marcarlo como cancelado
    public function cancelTransferOutBuffer(TransferOutBuffer $transfer_out_buffer)
    {
        try
        {
            // begin transaction.
            CompanyContext::getDb()->beginTransaction();

            // Get $transfer_out_buffer (for update).
            $transfer_out_buffer = Doctrine_Query::create()->from('TransferOutBuffer')->where('id = ?',$transfer_out_buffer->id)->forUpdate(true)->execute()->getFirst();

            // Tiene entry?, en caso afirmativo, lockea y verifica status.
            $transfer_lot_entry = null;
            if ($transfer_out_buffer->transfer_lot_entry_id)
            {
                // Get transfer lot entry (for update).
                $transfer_lot_entry = $this->getTransferLotEntryById($transfer_out_buffer->transfer_lot_entry_id,true);

                // Get transfer lot (for update).
                $transfer_lot = $this->getTransferLotById($transfer_lot_entry->transfer_lot_id,true);

                // Se fija el status del transfer_lot.
                if ($transfer_lot->status_id != TransferLotEntryStatus::PENDING)
                    throw new Exception(sprintf("The transfer_lot '%s' does not have PENDING status.",$transfer_lot->id));
            }
            else
            {
                // No tiene entry.
            }

            // Cancel transfer_out_buffer. Se hace seteando el flag, y limpiando el entry.
            $transfer_out_buffer->transfer_lot_entry_id = null;
            $transfer_out_buffer->is_cancelled = 1;
            $transfer_out_buffer->cancelled_at = date("Y-m-d H:i:s");
            $transfer_out_buffer->save();

            // Tiene entry?, en caso afirmativo, lockea y verifica status.
            if ($transfer_lot_entry instanceof TransferLotEntry)
            {
                $transfer_lot_entry->delete();
            }

            // commit transaction.
            CompanyContext::getDb()->commit();

            // done.
        }
        catch(Exception $e)
        {
            // rollback transaction.
            CompanyContext::getDb()->rollback();

            // re-throw same exception.
            throw $e;
        }
    }

    public function getTransferLotEntriesCountByLotId($transfer_lot_id)
    {
        // check input.
        $transfer_lot_id = (int)$transfer_lot_id;
        if ($transfer_lot_id <= 0)
            throw new Exception("Invalid transfer_lot_id");

        // get transfer lot.
        $transfer_lot_entries_count = Doctrine_Query::create()
            ->from('TransferLotEntry tle')
            ->where('transfer_lot_id = ?', $transfer_lot_id)
            ->count();

        if ($transfer_lot_entries_count <= 0)
            throw new Exception(sprintf("No transfer entries found for lot with id '%d'.",$transfer_lot_id));

        return $transfer_lot_entries_count;
    }

    public function getExternalAccountOwnerNameByAccountId($external_account_id)
    {
        if ($external_account_id instanceof ExternalAccount)
        {
            if ($external_account_id->type_id != ExternalAccountType::BANK)
                throw new Exception(sprintf("The type_id of external account '%s' is not BANK.",$external_account_id->id));
            if ($external_account_id->owner_type_id != ExternalAccountOwnerType::USER)
                throw new Exception(sprintf("The owner_type_id of external account '%s' is not USER.",$external_account_id->id));
            return $external_account_id->OwnerUser->last_name.' '.$external_account_id->OwnerUser->first_name;
        }
        else
        {
            // check input.
            $external_account_id = (int)$external_account_id;
            if ($external_account_id <= 0)
                throw new Exception("Invalid external_account_id");

            $external_account = Doctrine_query::create()
                ->from('ExternalAccount ea')
                ->innerJoin('ea.OwnerUser u')
                ->where('ea.id = ?', $external_account_id)
                ->andWhere('ea.owner_type_id = ?', ExternalAccountOwnerType::USER)
                ->andWhere('ea.type_id = ?', ExternalAccountType::BANK)
                // ->andWhere('ea.external_account_status_id = ?', ExternalAccountStatus::ENABLED)
                ->execute()
                ->getFirst();

            if (!($external_account instanceof ExternalAccount))
                throw new Exception(sprintf("Invalid ExternalAccount with id '%d'.",$external_account_id));

            // returns a string with the user's "[last name] [first name]"
            return $external_account->OwnerUser->last_name.' '.$external_account->OwnerUser->first_name;
        }
    }

    public function getExternalAccountOwnerCuitByAccountId($external_account_id)
    {
        if ($external_account_id instanceof ExternalAccount)
        {
            if ($external_account_id->type_id != ExternalAccountType::BANK)
                throw new Exception(sprintf("The type_id of external account '%s' is not BANK.",$external_account_id->id));
            if ($external_account_id->owner_type_id != ExternalAccountOwnerType::USER)
                throw new Exception(sprintf("The owner_type_id of external account '%s' is not USER.",$external_account_id->id));
            return $external_account_id->OwnerUser->Fdata->FdataChunkTax[0]->cuit;
        }
        else
        {
            // check input.
            $external_account_id = (int)$external_account_id;
            if ($external_account_id <= 0)
                throw new Exception("Invalid external_account_id");

            $external_account = Doctrine_query::create()
                ->from('ExternalAccount ea')
                ->innerJoin('ea.OwnerUser u')
                ->innerJoin('u.Fdata f')
                ->innerJoin('f.FdataChunkTax fct')
                ->where('ea.id = ?', $external_account_id)
                ->andWhere('ea.owner_type_id = ?', ExternalAccountOwnerType::USER)
                ->andWhere('ea.type_id = ?', ExternalAccountType::BANK)
                // ->andWhere('ea.external_account_status_id = ?', ExternalAccountStatus::ENABLED)
                ->execute()
                ->getFirst();

            if (!($external_account instanceof ExternalAccount))
                throw new Exception(sprintf("Invalid ExternalAccount with id '%d'.",$external_account_id));

            // returns a string with the user's cuit
            return $external_account->OwnerUser->Fdata->FdataChunkTax[0]->cuit;
        }
    }

    public function getExternalAccountBankCbuByAccountId($external_account_id, $owner_type_id)
    {
        if ($external_account_id instanceof ExternalAccount)
        {
            if ($external_account_id->type_id != ExternalAccountType::BANK)
                throw new Exception(sprintf("The type_id of external account '%s' is not BANK.",$external_account_id->id));
            if ($external_account_id->owner_type_id != $owner_type_id)
                throw new Exception(sprintf("Owner_type_id from external account '%s' mismatches with '%s'.",$external_account_id->owner_type_id,$owner_type_id));
            return $external_account_id->ExternalAccountTypeBank[0]->cbu;
        }
        else
        {
            // check input.
            $external_account_id = (int)$external_account_id;
            if ($external_account_id <= 0)
                throw new Exception("Invalid external_account_id");

            // 2014-08-22: Agregamos owner_type_id.
            $owner_type_id = (int)$owner_type_id;
            if ($owner_type_id <= 0)
                throw new Exception(sprintf("Invalid owner_type_id."));

            // Do query.
            $external_account = Doctrine_query::create()
                ->from('ExternalAccount ea')
                ->innerJoin('ea.ExternalAccountTypeBank eatb')
                ->where('ea.id = ?', $external_account_id)
                ->andWhere('ea.owner_type_id = ?', $owner_type_id)
                ->andWhere('ea.type_id = ?', ExternalAccountType::BANK)
                // ->andWhere('ea.external_account_status_id = ?', ExternalAccountStatus::ENABLED)
                ->execute()
                ->getFirst();
            if (!($external_account instanceof ExternalAccount))
                throw new Exception(sprintf("Invalid ExternalAccount with id '%d'.",$external_account_id));

            // returns a string with the user's CBU
            return $external_account->ExternalAccountTypeBank[0]->cbu;
        }
    }

    public function createCbuLot()
    {
        try
        {
            // begin transaction.
            CompanyContext::getDb()->beginTransaction();

            // Get external accounts (for update).
            $external_accounts = CompanyContext::getTransferLot()->_getExternalAccountsWithoutLotEntry(null,true);

            // if nothing to do, get out of here...
            if($external_accounts->count() <= 0)
            {
                // commit transaction.
                CompanyContext::getDb()->commit();
                return null;
            }

            // Set array de transfer lots.
            $transfer_lot_by_provider_id = array();

            // Walk over external accounts.
            foreach ($external_accounts as $external_account)
            {
                // FIXME: 2014-08-29: Cuando haya mas providers, hay que hacer esta elección dinámica por un peso de la base.
                $transfer_lot_provider_id = null;
                $transfer_lot_provider_to_enum_banks = $external_account->ExternalAccountTypeBank[0]->EnumBank->TransferLotProviderToEnumBank;
                switch (count($transfer_lot_provider_to_enum_banks))
                {
                case 1:
                    // Si sólo hay 1, entonces tengo que elegir el que venga.
                    $transfer_lot_provider_id = $transfer_lot_provider_to_enum_banks[0]->transfer_lot_provider_id;
                    break;
                case 2:
                    // En caso de que haya 2, ya se que tengo que elegir Interbanking.
                    $transfer_lot_provider_id = TransferLotProvider::INTERBANKING;
                    break;
                default:
                    throw new Exception(sprintf("Invalid count '%s' for transfer lot provider to enum bank.",count($transfer_lot_provider_to_enum_banks)));
                }

                // The transfer_lot_provider_id is not valid.
                if (!$transfer_lot_provider_id)
                    throw new Exception(sprintf("Unable to calculate transfer_lot_provider_id."));

                // Create a transfer_lot (if necesary).
                if (!array_key_exists($transfer_lot_provider_id,$transfer_lot_by_provider_id))
                {
                    $transfer_lot_by_provider_id[$transfer_lot_provider_id] = new TransferLot();
                    $transfer_lot_by_provider_id[$transfer_lot_provider_id]->type_id = TransferLotType::CBU_UPLOAD;
                    $transfer_lot_by_provider_id[$transfer_lot_provider_id]->status_id = TransferLotEntryStatus::PENDING;
                    $transfer_lot_by_provider_id[$transfer_lot_provider_id]->transfer_lot_provider_id = $transfer_lot_provider_id;
                    $transfer_lot_by_provider_id[$transfer_lot_provider_id]->save();
                }

                // Create new tranfer lot entry.
                $transfer_lot_entry = new TransferLotEntry();
                $transfer_lot_entry->transfer_lot_id = $transfer_lot_by_provider_id[$transfer_lot_provider_id]->id;
                $transfer_lot_entry->external_account_id = $external_account->id;
                $transfer_lot_entry->transfer_out_buffer_id = null;
                $transfer_lot_entry->save();

                // the external account was locked before, waiting for us to set the FK id, so I set it.
                $external_account->transfer_lot_entry_id = $transfer_lot_entry->id;
                $external_account->save();
            }

            // commit transaction.
            CompanyContext::getDb()->commit();

            // done.
            return $transfer_lot_by_provider_id;
        }
        catch(Exception $e)
        {
            // rollback transaction.
            CompanyContext::getDb()->rollback();

            // re-throw same exception.
            throw $e;
        }
    }

    public function createTransferLot($transfer_lot_provider_id)
    {
        // check input.
        $transfer_lot_provider_id = (int)$transfer_lot_provider_id;
        if ($transfer_lot_provider_id <= 0)
            throw new Exception("Invalid transfer_lot_provider_id");

        try
        {
            // begin transaction.
            CompanyContext::getDb()->beginTransaction();

            // change this if else the day we have more than one transfer lot provider other than interbanking and manual
            $tob_entries = CompanyContext::getTransferLot()->_getTransferOutBufferEntriesWithoutLotEntry($transfer_lot_provider_id, true);

            // if nothing to do, get out of here...
            if($tob_entries->count() <= 0)
            {
                // commit transaction.
                CompanyContext::getDb()->commit();
                return null;
            }

            // create and save a new lot
            $transfer_lot = new TransferLot();
            $transfer_lot->type_id = TransferLotType::TRANSFER_OUT;
            $transfer_lot->status_id = TransferLotEntryStatus::PENDING;
            $transfer_lot->transfer_lot_provider_id = $transfer_lot_provider_id;
            $transfer_lot->save();

            // Walk over TransferOutBuffers.
            foreach ($tob_entries as $transfer_out_buffer)
            {
                // Create new tranfer lot entry.
                $transfer_lot_entry = new TransferLotEntry();
                $transfer_lot_entry->transfer_lot_id = $transfer_lot->id;
                $transfer_lot_entry->external_account_id = null;
                $transfer_lot_entry->transfer_out_buffer_id = $transfer_out_buffer->id;
                $transfer_lot_entry->save();

                // the transfer_out_buffer was locked before, waiting for us to set the FK id, so I set it.
                $transfer_out_buffer->transfer_lot_entry_id = $transfer_lot_entry->id;
                $transfer_out_buffer->save();
            }

            // commit transaction.
            CompanyContext::getDb()->commit();

            // done.
            return $transfer_lot;
        }
        catch(Exception $e)
        {
            // rollback transaction.
            CompanyContext::getDb()->rollback();

            // re-throw same exception.
            throw $e;
        }
    }

    public function generateLotFile(TransferLot $transfer_lot)
    {
        switch ($transfer_lot->type_id)
        {
        case TransferLotType::CBU_UPLOAD:
            return $this->_generateCbuLotFile($transfer_lot);

        case TransferLotType::TRANSFER_OUT:
            return $this->_generateTransferLotFile($transfer_lot);

        default:
            throw new Exception(sprintf("Unimplmented transfer_lot_type_id: %s",$transfer_lot->type_id));
        }
    }

    // generates a text file with fixed registry lengths with the CBUs meant to be added to the provider's system
    private function _generateCbuLotFile(TransferLot $transfer_lot)
    {
        try
        {
            // begin transaction.
            CompanyContext::getDb()->beginTransaction();

            // Get transfer_lot (for update).
            $transfer_lot = $this->getTransferLotById($transfer_lot->id,true);

            // get the cbu entries from this lot
            $transfer_lot_entries = $this->getTransferLotEntriesData($transfer_lot);

            // registry 1 of 3:
            // desde hasta total (1 + 7 + 152)
            // 01    01    (1)   -> Tipo de registro  - Se debe ingresar el número 1 (uno).
            // 02    08    (7)   -> Código de Cliente - Por ejemplo: A12345A -> our company uses a B for Begin and our lot id.
            // 09    160   (152) -> Resto 1           - Debe estar en blanco.
            $output_buffer = sprintf("1%-7s%-152s".self::LINE_BREAKS, self::CLIENT_CODE, null);

            // registry 2 of 3:
            // desde hasta total (1 + 7 + 152)
            // 01    01    (1)   -> Tipo de registro     - Se debe ingresar el número 2 (dos).
            // 02    23    (22)  -> No utilizados        - Debe estar en blanco
            // 24    52    (29)  -> Denominación Cuenta  - Indicar la denominación de la cuenta.
            // 53    53    (1)   -> Uso Proveedores      - S: SE ASIGNA EL USO PROVEEDORES. N: no asigna el uso proveedores. -> FOR COMPANY IT'S S
            // 54    54    (1)   -> Uso Sueldos          - S: se asigna el uso sueldos. N: NO ASIGNA EL USO SUELDOS. -> FOR COMPANY IT'S N
            // 55    55    (1)   -> Uso Pagos Judiciales - S: se asigna el uso Pagos Judiciales. N: NO ASIGNA EL USO PAGOS JUDICIALES. -> FOR COMPANY IT'S N
            // 56    66    (11)  -> CUIT                 - Número de CUIT del titular de la cuenta (obligatorio).
            // 67    88    (22)  -> CBU                  - Número de CBU (obligatorio).
            // 89    160   (72)  -> Resto 2              - Debe estar en blanco.
            foreach ($transfer_lot_entries as $transfer_lot_entry)
            {
                $output_buffer .= sprintf("2%-22s%-29sSNN%-11s%-22s%-72s".self::LINE_BREAKS,
                    null, // (22)  -> No utilizados        - Debe estar en blanco
                    substr($transfer_lot_entry->data->name,0,29), // (29)  -> Denominación Cuenta  - Indicar la denominación de la cuenta.
                    $transfer_lot_entry->data->cuit, // (11)  -> CUIT                 - Número de CUIT del titular de la cuenta (obligatorio).
                    $transfer_lot_entry->data->cbu, // (22)  -> CBU                  - Número de CBU (obligatorio).
                    null
                );
            }

            // registry 3 of 3:
            // desde hasta total (1 + 7 + 152)
            // 01    01    (1)   -> Tipo de registro  - Se debe ingresar el número 3 (tres).
            // 02    08    (7)   -> Código de Cliente - Por ejemplo: A12345A -> our company uses a E for End and our lot id.
            // 09    14    (6)   -> Total de Cuentas  - Cantidad de cuentas crédito que contiene el archivo.
            // 15    160   (146) -> Resto 3           - Debe estar en blanco.
            $output_buffer .= sprintf("3%-7s%06d%-146s", self::CLIENT_CODE, count($transfer_lot_entries), null);

            // tag the transfer lot as sent
            if ($transfer_lot->status_id == TransferLotEntryStatus::PENDING)
            {
                $transfer_lot->status_id = TransferLotEntryStatus::SENT;
                $transfer_lot->sent_at = date('Y-m-d H:i:s');
                $transfer_lot->save();
            }

            // commit transaction.
            CompanyContext::getDb()->commit();

            // done.
            return $output_buffer;
        }
        catch(Exception $e)
        {
            // rollback transaction.
            CompanyContext::getDb()->rollback();

            // re-throw same exception.
            throw $e;
        }
    }

    // generates a text file with fixed registry lengths with the transfers meant to be transfered by the provider system
    private function _generateTransferLotFile(TransferLot $transfer_lot)
    {
        try
        {
            // begin transaction.
            CompanyContext::getDb()->beginTransaction();

            // Get transfer_lot (for update).
            $transfer_lot = $this->getTransferLotById($transfer_lot->id,true);

            // get the cbu entries from this lot
            $transfer_lot_entries = $this->getTransferLotEntriesData($transfer_lot);

            // Calcula el manager external account id.
            $manager_external_account_id = null;
            foreach ($transfer_lot_entries as $transfer_lot_entry)
            {
                // Traemos el external_account del manager.
                $new_manager_external_account_id = CompanyContext::getExternalAccount()->getManagerExternalAccountFromExternalAccount(
                    $transfer_lot_entry->data->external_account_id,  // $external_account_id,
                    TransferDirection::DEBIT,                        // $transfer_direction_id,
                    ExternalAccountType::BANK                        // $external_account_type_id = null
                );

                // Setea el external account id del manager.
                if (is_null($manager_external_account_id))
                    $manager_external_account_id = $new_manager_external_account_id;
                elseif ($manager_external_account_id != $new_manager_external_account_id)
                    throw new Exception(sprintf("El lot '%s' tiene mas de un manager_external_account_id, y esto no es posible.",$transfer_lot->id));
            }
            if (is_null($manager_external_account_id))
                throw new Exception(sprintf("Unable to calculate manager_external_account_id."));

            // Init buffer.
            $output_buffer = '';

            // if asked to generate a file for a manual provider, return a simpler version of the output buffer that is easier for humans to read:
            if ($transfer_lot->transfer_lot_provider_id == TransferLotProvider::MANUAL)
            {
                $output_buffer .= "ARCHIVO DE TRANSFERENCIAS MANUALES\n\n";
                $output_buffer .= sprintf(
                    "CBU fuente: '%-22s', observación: 'S%-61s', fecha: '%-08s', id: '%-08s'\n",
                    CompanyContext::getTransferLot()->getExternalAccountBankCbuByAccountId($manager_external_account_id,ExternalAccountOwnerType::MANAGER), // (22)  -> Número de CBU
                    $transfer_lot->id.'_'.trim($manager_external_account_id), // (61)  -> Observación del lote.
                    date("m/d/y"), // (08)  -> Fecha del archivo en formato MM/DD/YY
                    $transfer_lot->id // (08)  -> Nro. de secuencia del archivo. Este dato es opcional, y puede ser útil para evitar importar el mismo archivo dos veces.
                );

                foreach ($transfer_lot_entries as $transfer_lot_entry)
                {
                    $output_buffer .= sprintf(
                        "CBU destino: '%-22s', importe: '%s', observación: '%-60s'\n",
                        $transfer_lot_entry->data->cbu, // (22)  -> Número de CBU
                        round($transfer_lot_entry->data->transfer_amount,2), // int(15)  -> Importe de la transferencia.
                        $transfer_lot_entry->id // (60)  -> Observaciones (Opcional)
                    );
                }
            }
            elseif ($transfer_lot->transfer_lot_provider_id == TransferLotProvider::INTERBANKING)
            {
                // Lot creation datetime.
                $lot_creation_datetime = new DateTime('now');
                $lot_creation_datetime = $lot_creation_datetime->format('Ymd');

                // header line:
                // desde hasta total (1 + 7 + 152)
                // 01    03    (03)  -> Tipo De registro. Contiene (*U*)
                // 04    25    (22)  -> Número de CBU
                // 26    26    (1)   -> Indicador de Débito o Crédito. Ingrese “D” o “C”
                // 27    34 int(8)   -> Fecha de solicitud en formato AAAAMMDD
                // 35    35    (1)   -> Marca de consolidado. Indique “S” o “N” - Esta marca debe coincidir con lo que posteriormente indicará en la pantalla de confección de la transferencia.
                // 36    96    (61)  -> Observación del lote.
                // 97    99 int(03)  -> Ingrese 000 (triple cero)
                // 100   101int(02)  -> Nro. de cuenta corto según formato Datanet. Ingrese siempre 00 (doble cero).
                // 102   109   (08)  -> Fecha del archivo en formato MM/DD/YY
                // 110   117   (08)  -> Nro. de secuencia del archivo. Este dato es opcional, y puede ser útil para evitar importar el mismo archivo dos veces.
                // 118   240   (123) -> Espacios en blanco
                $output_buffer .= sprintf(
                    "*U*%-22sD%08dN%-61s00000%-08s%-08s%-123s".self::LINE_BREAKS,
                    CompanyContext::getTransferLot()->getExternalAccountBankCbuByAccountId($manager_external_account_id,ExternalAccountOwnerType::MANAGER), // (22)  -> Número de CBU from Company
                    $lot_creation_datetime, // int(8)   -> Fecha de solicitud en formato AAAAMMDD
                    '', // (61)  -> Observación del lote.
                    date("m/d/y"), // (08)  -> Fecha del archivo en formato MM/DD/YY
                    str_pad($transfer_lot->id,8,'0',STR_PAD_LEFT), // (08)  -> Nro. de secuencia del archivo. Este dato es opcional, y puede ser útil para evitar importar el mismo archivo dos veces.
                    null // (123) -> Espacios en blanco
                );

                // transfer info lines:
                // desde hasta total (1 + 7 + 152)
                // 01    03    (03)  -> Tipo de registro. Contiene (*M*)
                // 04    25    (22)  -> Número de CBU
                // 26    42 int(15)  -> Importe de la transferencia. Por ejemplo:
                //                                   Si el importe de la transferencia es $ 100,00, se deberá ingresar el valor 00000000000010000
                //                                   Si el importe de la transferencia es $ 150,25, se deberá ingresar el valor 00000000000015025
                // 43    102   (60)  -> Observaciones (Opcional)
                // 103   104int(02)  -> Nro. de cuenta corto según formato Datanet. Ingrese siempre 00 (doble cero).
                // 105   240   (136) -> Espacios en blanco
                foreach ($transfer_lot_entries as $transfer_lot_entry)
                {
                    /*
                    $output_buffer .= sprintf(
                        "*M*%-22s%015d%-60s00%-136s".self::LINE_BREAKS,
                        $transfer_lot_entry->data->cbu, // (22)  -> Número de CBU
                        round($transfer_lot_entry->data->transfer_amount*100), // int(15)  -> Importe de la transferencia.
                        $transfer_lot_entry->id,
                        null // (136) -> Espacios en blanco
                    );
                    */

                    // Init strings and args.
                    $strings = '';
                    $args = array();

                    // Registros `*M*` para TEF de Proveedores
                    // ---------------------------------------
                    // desde  hasta  total
                    // -----  -----  -----
                    // 01     03     X(03)     -> Tipo De registro. Contiene (*M*)
                    $strings .= "*M*";

                    // 04     25     X(22)     -> Número de CBU
                    $strings .= "%-22s";
                    $args[] = $transfer_lot_entry->data->cbu; // X(22)     -> Número de CBU

                    // 26     42     9(15)v99  -> Importe de la transferencia. (**) -> OJO QUE EN LA DOC DICE 15, PERO HAY QUE USAR 17.
                    $strings .= "%017d";
                    $args[] = round($transfer_lot_entry->data->transfer_amount*100);

                    // 43     102    X(60)     -> Observaciones
                    $strings .= "%-60s";
                    $args[] = $transfer_lot_entry->id;

                    // 103    104    X(02)     -> Documento a cancelar (Por ejemplo, FA: Factura / DB: Nota de Débito)
                    $strings .= "%-2s";
                    $args[] = '';

                    // 105    116    X(12)     -> Número de documento a cancelar
                    $strings .= "%-12s";
                    $args[] = '';

                    // 117    118    X(02)     -> Tipo de orden de pago
                    $strings .= "%-2s";
                    $args[] = '';

                    // 119    130    X(12)     -> Número de orden de pago
                    $strings .= "%-12s";
                    $args[] = '';

                    // 131    142    X(12)     -> Código de Cliente
                    $strings .= "%-12s";
                    $args[] = self::CLIENT_CODE;

                    // 143    144    X(02)     -> Tipo de retención (Por ejemplo, 01:IVA / 02: Ganancias / 03: Ingresos Brutos / 04: SUSS)
                    $strings .= "%-2s";
                    $args[] = '';

                    // 145    156    9(10)v99  -> Total de retención -> OJO QUE EN LA DOC DICE 10, PERO HAY QUE USAR 12.
                    $strings .= "%012d";
                    $args[] = 0;

                    // 157    168    X(12)     -> Número de nota de crédito
                    $strings .= "%-12s";
                    $args[] = '';

                    // 169    178    9(08)v99  -> Importe de la nota de crédito -> OJO QUE EN LA DOC DICE 8, PERO HAY QUE USAR 10.
                    $strings .= "%010d";
                    $args[] = 0;

                    // 179    189    X(11)     -> Número de CUIT
                    $strings .= "%-11s";
                    $args[] = $transfer_lot_entry->data->cuit;

                    // 190    240    X(51)     -> Espacios en blanco
                    $strings .= "%-51s";
                    $args[] = '';

                    // End string.
                    $strings .= self::LINE_BREAKS;

                    // Build params.
                    $params = array_merge(
                        array($strings),
                        $args
                    );

                    // Build output buffer.
                    $output_buffer .= call_user_func_array('sprintf',$params);
                }
            }
            else
            {
                throw new Exception(sprintf("Unimplemented transfer_log_provider_id: %s",$transfer_lot->transfer_lot_provider_id));
            }

            // tag the transfer lot as sent
            if ($transfer_lot->status_id == TransferLotEntryStatus::PENDING)
            {
                $transfer_lot->status_id = TransferLotEntryStatus::SENT;
                $transfer_lot->sent_at = date('Y-m-d H:i:s');
                $transfer_lot->save();
            }

            // commit transaction.
            CompanyContext::getDb()->commit();

            // done.
            return $output_buffer;
        }
        catch(Exception $e)
        {
            // rollback transaction.
            CompanyContext::getDb()->rollback();

            // re-throw same exception.
            throw $e;
        }
    }

    public function closeTransferLot(TransferLot $transfer_lot, $lock_hash)
    {
        // verify lock hash.
        if (!$lock_hash)
            throw new Exception("Invalid lock_hash");

        // Get transfer lot.
        if ($transfer_lot->status_id != TransferLotEntryStatus::SENT)
            throw new Exception(sprintf("Tried close transfer lot id '%d', but it was set as '%s' and not set as SENT", $transfer_lot->id, $transfer_lot->status_id));

        // Check flag.
        if ($transfer_lot->ok_flag != 1)
            throw new Exception(sprintf("Tried close transfer lot id '%d', but the ok_flag was not set.", $transfer_lot->id));

        // Check locked.
        if (!$transfer_lot->locked)
            throw new Exception(sprintf("Tried close transfer lot id '%d', but it's not locked.", $transfer_lot->id));

        // Lock transfer lots entries.
        $q = Doctrine_Query::create()->update('TransferLotEntry')
            ->set('locked','?',$lock_hash)
            ->set('locked_at','?',date('Y-m-d H:i:s'))
            ->set('error_msg','NULL')
            ->where('transfer_lot_id = ?',$transfer_lot->id)
            ->andWhere('is_closed = 0')
            ->andWhere('locked IS null')
            ->orderBy('id ASC')
        ;

        // Set limit 1 for transfer_out only.
        if ($transfer_lot->type_id == TransferLotType::TRANSFER_OUT)
        {
            $q->limit(1);
        }

        // Consegui algo para cerrar?
        $amout_of_locked_transfer_lot_entries = $q->execute();
        if ($amout_of_locked_transfer_lot_entries <= 0)
            throw new Exception(sprintf("Nothing to process for transfer_lot '%s'.",$transfer_lot->id));

        // Traigo los transfer lot entries lockeados.
        $transfer_lot_entries_for_close = Doctrine_Query::create()
            ->from('TransferLotEntry')
            ->where('locked = ?',$lock_hash)
            ->execute()
        ;
        if ($transfer_lot_entries_for_close->count() <= 0)
            throw new Exception(sprintf("No pude traer nada para cerrar."));

        // Walk over entries.
        foreach ($transfer_lot_entries_for_close as $transfer_lot_entry)
        {
            try
            {
                echo sprintf("+ Closing TransferLotEntry #%s: ",$transfer_lot_entry->id);

                // Cierra el transfer lot entry.
                if ($transfer_lot->type_id == TransferLotType::TRANSFER_OUT)
                {
                    CompanyContext::getTransfer()->closeOkTransfer(
                        $transfer_lot_entry->DirectTransferOutBuffer->transfer_id,        // $transfer_id
                        '0_TRANSFER_LOT_'.$transfer_lot->id.'_CLOSEOK_'.date('Ymd_His'),  // $action_cod
                        false,                                                            // $is_autom
                        $transfer_lot->ref_number,                                        // $ref_number
                        $transfer_lot->transfer_date,                                     // $transfer_date
                        $transfer_lot_entry->transfer_lot_id                              // $transfer_lot_id
                    );
                }
                elseif ($transfer_lot->type_id == TransferLotType::CBU_UPLOAD)
                {
                    // Graba el preferred_provider en el external_account.
                    $external_account = Doctrine_Query::create()->from('ExternalAccount')->where('id = ?',$transfer_lot_entry->external_account_id)->forUpdate(true)->execute()->getFirst();
                    if (!($external_account instanceof ExternalAccount))
                        throw new Exception(sprintf("Unable to find external_account '%s'.",$transfer_lot_entry->external_account_id));

                    // Set and save.
                    $external_account->preferred_provider_id = $transfer_lot->transfer_lot_provider_id;
                    $external_account->save();
                }
                else
                {
                    throw new Exception(sprintf("Unimplemented transfer_lot_type_id: %s",$transfer_lot->type_id));
                }

                // Seteo el flag del entry.
                Doctrine_Query::create()->update('TransferLotEntry')
                    ->set('is_closed','1')
                    ->set('locked','NULL')
                    ->set('locked_at','NULL')
                    ->set('closed_at','?',date('Y-m-d H:i:s'))
                    ->where('id = ?',$transfer_lot_entry->id)
                    ->limit(1)
                    ->execute()
                ;

                // print ok.
                echo "DONE\n";
            }
            catch (Exception $e)
            {
                // tags the register with an error mesagge.
                Doctrine_Query::create()->update('TransferLotEntry')
                    ->set('error_msg','?',$e->getMessage())
                    ->where('id = ?',$transfer_lot_entry->id)
                    ->limit(1)
                    ->execute()
                ;

                // Print error.
                echo "ERROR: Failed with Exception: ".$e->getMessage()."\n";

                // Handle error by email.
                CompanyErrorNotifierErrorHandler::handleException($e, 'CORE', true);
            }
        }

        // Trae el count de los que faltan.
        $remaining_cnt = Doctrine_Query::create()->from('TransferLotEntry')->where('transfer_lot_id = ?',$transfer_lot->id)->andWhere('is_closed = 0')->count();

        // Si ya no queda ninguno, lockeo el transfer lot y lo cierro.
        if ($remaining_cnt <= 0)
        {
            try
            {
                // begin transaction.
                CompanyContext::getDb()->beginTransaction();

                // Get transfer lot (for update).
                $transfer_lot = $this->getTransferLotById($transfer_lot->id,true);

                // Set transfer_lot data.
                $transfer_lot->status_id = TransferLotEntryStatus::OK;
                $transfer_lot->locked = null;
                $transfer_lot->locked_at = null;
                $transfer_lot->error_msg = null;
                $transfer_lot->save();

                // commit transaction.
                CompanyContext::getDb()->commit();

            }
            catch(Exception $e)
            {
                // rollback transaction.
                CompanyContext::getDb()->rollback();

                // re-throw same exception.
                throw $e;
            }
        }
        else
        {
            // En caso de que queden, simplemente lo des-lockeo, pero no le cambio el status.
            try
            {
                // begin transaction.
                CompanyContext::getDb()->beginTransaction();

                // Get transfer lot (for update).
                $transfer_lot = $this->getTransferLotById($transfer_lot->id,true);

                // Set transfer_lot data.
                $transfer_lot->locked = null;
                $transfer_lot->locked_at = null;
                $transfer_lot->error_msg = null;
                $transfer_lot->save();

                // commit transaction.
                CompanyContext::getDb()->commit();

            }
            catch(Exception $e)
            {
                // rollback transaction.
                CompanyContext::getDb()->rollback();

                // re-throw same exception.
                throw $e;
            }
        }
    }

    public function cancelTransferLot(TransferLot $transfer_lot)
    {
        try
        {
            // begin transaction.
            CompanyContext::getDb()->beginTransaction();

            // Get transfer lot (for update).
            $transfer_lot = $this->getTransferLotById($transfer_lot->id,true);

            // Get transfer lot.
            if ($transfer_lot->status_id != TransferLotEntryStatus::SENT)
                throw new Exception(sprintf("Tried close transfer lot id '%d', but it was set as '%s' and not set as SENT", $transfer_lot->id, $transfer_lot->status_id));

            // Check flag.
            if ($transfer_lot->ok_flag != 1)
                throw new Exception(sprintf("Tried close transfer lot id '%d', but the ok_flag was not set.", $transfer_lot->id));

            // Check locked.
            if ($transfer_lot->locked)
                throw new Exception(sprintf("Tried close transfer lot id '%d', but it's locked.", $transfer_lot->id));

            // Set transfer_lot data.
            $transfer_lot->ok_flag = 0;
            $transfer_lot->ok_at = null;
            $transfer_lot->ref_number = null;
            $transfer_lot->transfer_date = null;
            $transfer_lot->scheduled_at = null;
            $transfer_lot->save();

            // commit transaction.
            CompanyContext::getDb()->commit();
        }
        catch(Exception $e)
        {
            // rollback transaction.
            CompanyContext::getDb()->rollback();

            // re-throw same exception.
            throw $e;
        }
    }

    public function setTransferLotOkFlag(TransferLot $transfer_lot, $ref_number, $transfer_date)
    {
        try
        {
            // begin transaction.
            CompanyContext::getDb()->beginTransaction();

            // Get transfer lot (for update).
            $transfer_lot = $this->getTransferLotById($transfer_lot->id,true);

            // Get transfer lot.
            if ($transfer_lot->status_id != TransferLotEntryStatus::SENT)
                throw new Exception(sprintf("Tried to set transfer lot id '%d' to OK flag, but it was set as '%s' and not set as SENT.", $transfer_lot->id, $transfer_lot->status_id));

            // Check flag.
            if ($transfer_lot->ok_flag)
                throw new Exception(sprintf("Tried to set transfer lot id '%d' to OK flag, but it was already set.", $transfer_lot->id));

            // Calculate scheduled_at.
            $now = new DateTime('now');
            $scheduled_at = clone $now;
            $scheduled_at->modify('+30 minute');

            // Set flag.
            $transfer_lot->ok_flag = 1;
            $transfer_lot->ok_at = date('Y-m-d H:i:s');
            $transfer_lot->ref_number = $ref_number;
            $transfer_lot->transfer_date = $transfer_date;
            $transfer_lot->scheduled_at = $scheduled_at->format('Y-m-d H:i:s');
            $transfer_lot->save();

            // commit transaction.
            CompanyContext::getDb()->commit();
        }
        catch(Exception $e)
        {
            // rollback transaction.
            CompanyContext::getDb()->rollback();

            // re-throw same exception.
            throw $e;
        }
    }

    public function getTransferOutBufferByTransfer(Transfer $transfer)
    {
        // Get transfer_out_buffers.
        $transfer_out_buffers = Doctrine_Query::create()->from('TransferOutBuffer')->where('transfer_id = ?',$transfer->id)->execute();
        if ($transfer_out_buffers->count() <= 0)
            return null;
        if ($transfer_out_buffers->count() > 1)
            throw new Exception(sprintf("Found more than 1 transfer_out_buffer associated to this transfer_id '%s'.",$transfer->id));
        $transfer_out_buffer = $transfer_out_buffers->getFirst();
        if (!($transfer_out_buffer instanceof TransferOutBuffer))
            throw new Exception(sprintf("Unable to get transfer_out_buffer."));

        // done.
        return $transfer_out_buffer;
    }

    public function getTransferLotEntriesData(TransferLot $transfer_lot)
    {
        switch ($transfer_lot->type_id)
        {
        case TransferLotType::CBU_UPLOAD:
            $query = "select
               tle.*,
               ea.id as data_external_account_id,
               eatb.cbu as data_cbu,
               concat(u.last_name,' ',u.first_name) as data_name,
               t.cuit as data_cuit
            from transfer_lot_entry tle
              inner join external_account ea on (tle.external_account_id = ea.id and ea.type_id = 1 and ea.owner_type_id = 1)
              inner join external_account_type_bank eatb on eatb.account_id = ea.id
              inner join user u on u.id = ea.owner_id
              inner join fdata_chunk_tax t on t.fdata_id = u.fdata_id
            where tle.transfer_lot_id = ?
            group by 1
            ;";
            break;

        case TransferLotType::TRANSFER_OUT:
            $query = "select
               tle.*,
               ea.id as data_external_account_id,
               tob.transfer_amount as data_transfer_amount,
               tr.created_at as data_created_at,
               eatb.cbu as data_cbu,
               concat(u.last_name,' ',u.first_name) as data_name,
               t.cuit as data_cuit
            from transfer_lot_entry tle
              inner join transfer_out_buffer tob on tob.id = tle.transfer_out_buffer_id
              inner join transfer tr on tr.id = tob.transfer_id
              inner join external_account ea on (ea.id = tr.external_account_id and ea.type_id = 1 and ea.owner_type_id = 1)
              inner join external_account_type_bank eatb on eatb.account_id = ea.id
              inner join user u on u.id = ea.owner_id
              inner join fdata_chunk_tax t on t.fdata_id = u.fdata_id
            where tle.transfer_lot_id = ?
            group by 1
            ;";
            break;

        default:
            throw new Exception(sprintf("Unimplemented type_id: %s",$transfer_lot->type_id));
        }

        // Build entries.
        $entries = array();
        foreach (CompanyContext::getDb()->execute($query,array($transfer_lot->id))->fetchAll() as $row)
        {
            $entry = new StdClass();
            $entry->data = new StdClass();
            foreach ($row as $key => $val)
            {
                if (!is_numeric($key))
                {
                    $matches = null;
                    if (preg_match('/^data_([a-z0-9_]+)$/',$key,$matches))
                        $entry->data->{$matches[1]} = $val;
                    else
                        $entry->$key = $val;
                }
            }
            $entries[$entry->id] = $entry;
        }

        // Verifica que el count coincida.
        $cnt = $this->getTransferLotEntriesCountByLotId($transfer_lot->id);
        if ($cnt != count($entries))
        {
            throw new Exception(sprintf("Entry_count mismatch '%s' vs. '%s'.",$cnt,count($entries)));
        }

        // done.
        return $entries;
    }
}
