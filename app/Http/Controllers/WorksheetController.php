<?php

namespace App\Http\Controllers;

use App\Models\CarPartProcess;
use App\Models\LabourProcess;
use App\Models\Maintenance;
use App\Models\MaterialProcess;
use App\Models\User;
use App\Models\Worksheet;
use Carbon\Carbon;
use DateTime;
use DateTimeZone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WorksheetController extends Controller
{
    function asc($a, $b)
    {
        // CONVERT $a AND $b to DATE AND TIME using strtotime() function
        $t1 = new DateTime($a["created_at"]);
        $t2 = new DateTime($b["created_at"]);
        if ($t1 === $t2) return 0;
        return ($t1 < $t2) ? -1 : 1;
    }
    function desc($a, $b)
    {
        // CONVERT $a AND $b to DATE AND TIME using strtotime() function
        $t1 = new DateTime($a["created_at"]);
        $t2 = new DateTime($b["created_at"]);
        if ($t1 === $t2) return 0;
        return ($t1 > $t2) ? -1 : 1;
    }

    public function getWorksheets($search, $query)
    {
        $worksheets = NULL;
        if (isset($search)) {
            $worksheets = $query
                ->where('customer_name', 'LIKE', "%{$search}%")
                ->orWhere('customer_addr', 'LIKE', "%{$search}%")
                ->orWhere('vehicle_license', 'LIKE', "%{$search}%")
                ->orWhere('vehicle_brand', 'LIKE', "%{$search}%")
                ->orWhere('vehicle_model', 'LIKE', "%{$search}%");
        } else {
            $worksheets = $query;
        }

        return $worksheets->get();
    }

    public function deleteProcess(Request $request, $worksheetId, $type, $id)
    {
        $bigcheck = explode($request->server('HTTP_ORIGIN'), $request->server('HTTP_REFERER'))[1];

        if ($bigcheck == '/worksheets/' . $worksheetId) {
            switch ($type) {
                case 1:
                    LabourProcess::find($id)->delete();
                    break;
                case 2:
                    CarPartProcess::find($id)->delete();
                    break;
                case 3:
                    MaterialProcess::find($id)->delete();
                    break;
                case 4:
                    LabourProcess::find($id)->delete();
                    break;
            }
        }
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        if (Auth::check()) {
            $q = Worksheet::query();
            $order = isset($date) ? $request->query('date') : 'desc';
            if (Auth::user()->role_id === 1) {
                $date = $request->query('date');
                $closed = $request->query('closed');
                if (isset($closed)) {
                    if ($closed === 'true') {
                        $q = $q->having('closed', '=', 1);
                    } else {
                        $q = $q->having('closed', '=', 0);
                    }
                }
                $q = $q->orderBy('created_at', $order);
                $worksheets = $this->getWorksheets($request->query('search'), $q);
            } else {
                $q = $q->having('mechanic_id', '=', Auth::user()->id)->having('closed', '=', 0);
                $q = $q->orderBy('created_at', $order);
                $worksheets = $this->getWorksheets($request->query('search'), $q);
                $closed = 'false';
            }
            return view('pages.worksheets', ['closed' => $closed, 'order' => $order, 'search' => $request->query('search'), 'worksheets' => $worksheets]);
        } else return redirect('/');
    }
    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        if (Auth::check() && Auth::user()->role_id === 1) {
            $datetime = Carbon::now()->toDateTimeLocalString();
            $mechanics = User::all();
            return view('pages.worksheets_create', ['mechanics' => $mechanics, 'datetime' => $datetime]);
        } else return redirect('/');
    }

    public function downloadPDF($id)
    {
        $ws = Worksheet::find($id);
        $labours = $this->getLabours($ws);
        $price = 0;
        foreach ($labours as $l) {
            $price += $l['price'];
        }

        return view('worksheet_pdf', ['worksheet' => $ws, 'labours' => $labours, 'price' => $price]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        if (Auth::check() && Auth::user()->role_id === 1) {
            $ws = Worksheet::create([
                'admin_id' => Auth::user()->id,
                'customer_name' => isset($request->customer_name) ? $request->customer_name : NULL,
                'customer_addr' => isset($request->customer_addr) ? $request->customer_addr : NULL,
                'vehicle_license' => isset($request->vehicle_license) ? $request->vehicle_license : NULL,
                'vehicle_brand' => isset($request->vehicle_brand) ? $request->vehicle_brand : NULL,
                'vehicle_model' => isset($request->vehicle_model) ? $request->vehicle_model : NULL,
                'customer_addr' => isset($request->customer_addr) ? $request->customer_addr : NULL,
            ]);



            return redirect()->intended('worksheets')->with(['alert' => [
                'type' => 'success',
                'message' => 'Munkalap létrehozva "' . (isset($ws->customer_name) ? $ws->customer_name . ' - ' . $ws->id : 'Munkalap - ' . $ws->id) . '" néven!'
            ]]);
        } else return redirect('/');
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }
    public function addAdditionalData($array)
    {
        $result = [];
        foreach ($array as $labour) {
            if ($labour['maintenance_id'] != null) {
                $coll = Maintenance::find($labour['maintenance_id']);
                $labour = array_merge($labour, ['name' => $coll->name]);
            }
            array_push($result, $labour);
        }
        return $result;
    }

    public function convertToArray($collection, $arrayOfInclude = [])
    {
        $result = [];
        foreach ($collection as $el) {
            $array = [];
            $created_at = $el->created_at;
            $array = array_merge($array, $el->toArray());
            $array = array_merge($array, ['created_at' => $created_at]);
            $array = array_merge($array, $arrayOfInclude);

            if ($el->maintenance_id != null) {
                $name = Maintenance::find($el->maintenance_id)->name;
                $array = array_merge($array, ['name' => $name]);
            }
            array_push($result, $array);
        }
        return $result;
    }

    public function getLabours($worksheet)
    {
        $lp = $this->convertToArray($worksheet->labour_process, ['type' => 1]);
        $ucp = $this->convertToArray($worksheet->used_car_parts, ['type' => 2]);
        $um = $this->convertToArray($worksheet->used_materials, ['type' => 3]);

        return array_merge($lp, $ucp, $um);
    }
    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $worksheet = Worksheet::where('id', $id)->get()->first();
        if ($worksheet === null) {
            return redirect('/worksheets')->with(['alert' => ['type' => 'danger', 'message' => 'Nem létezik ez a munkalap!']]);
        }
        if ($worksheet->closed == 1 && Auth::user()->role_id == 2) {
            return redirect('/worksheets')->with(['alert' => ['type' => 'danger', 'message' => 'A munkalap zárolva van!']]);
        }

        $mechanics = User::all();
        $worksheet['created_at_html'] = Carbon::createFromTimeString($worksheet['created_at'])->toDateTimeLocalString();

        $labours = $this->getLabours($worksheet);

        return view('pages.worksheets_edit', [
            'labour_processes' => $labours,
            'mechanics' => $mechanics,
            'worksheet' => $worksheet,
            'extendRouteName' => [
                'id' => $id
            ]
        ]);
    }
    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        if (Auth::check()) {

            if (Auth::user()->role_id === 1) {
                $ws = Worksheet::find($id);
                $curr_closed = $request->closed === 'on' ? 1 : 0;
                if ($curr_closed === 0 && $ws->closed === 1) {
                    $ws->update([
                        'closed' =>  $curr_closed,
                        'closed_at' => NULL,
                    ]);
                } else if ($curr_closed === 1 && $ws->closed === 0 || $curr_closed === 0 && $ws->closed === 0) {
                    Worksheet::find($id)->update([
                        'customer_name' => isset($request->customer_name) ? $request->customer_name : NULL,
                        'customer_addr' => isset($request->customer_addr) ? $request->customer_addr : NULL,
                        'vehicle_license' => isset($request->vehicle_license) ? $request->vehicle_license : NULL,
                        'vehicle_brand' => isset($request->vehicle_brand) ? $request->vehicle_brand : NULL,
                        'vehicle_model' => isset($request->vehicle_model) ? $request->vehicle_model : NULL,
                        'mechanic_id' => isset($request->mechanic_id) && $request->mechanic_id != -1 ? $request->mechanic_id : NULL,
                        'closed' => $request->closed === 'on' ? 1 : 0,
                        'closed_at' => $request->closed === 'on' ? Carbon::now('2') : NULL,
                        'payment' => $request->payment,
                        'updated_at' => Carbon::now('2')
                    ]);
                    if ($request->process !== null) {
                        $this->saveProcess($id, $request->process);
                    }
                }

                Worksheet::find($id)->update([
                    'payment' =>  $request->payment,
                ]);

                return redirect("worksheets/" . $id)->with(['alert' => [
                    'type' => 'success',
                    'message' => 'Munkalap mentve!'
                ]]);
            } else {
                if ($request->process !== null) {

                    $this->saveProcess($id, $request->process);


                    return redirect("worksheets/" . $id)->with(['alert' => [
                        'type' => 'success',
                        'message' => 'Munkalap mentve!'
                    ]]);
                } else {
                    return redirect("worksheets/" . $id)->with(['alert' => [
                        'type' => 'success',
                        'message' => 'Munkalap mentve!'
                    ]]);
                }
            }
        } else return redirect('/');
    }

    public function saveProcess($id, $processArray)
    {
        foreach ($processArray as $process) {
            switch ($process['process']) {
                case "1":
                    LabourProcess::create([
                        'worksheet_id' => $id,
                        'time_span' => $process['time_span'],
                        'maintenance_id' => $process['maintenance'],
                        'price' => $process['price']
                    ]);
                    break;
                case "2":
                    MaterialProcess::create([
                        'worksheet_id' => $id,
                        'name' => $process['name'],
                        'amount' => $process['amount'],
                        'price' => $process['price']
                    ]);
                    break;
                case "3":
                    CarPartProcess::create([
                        'worksheet_id' => $id,
                        'name' => $process['name'],
                        'serial' => $process['serial'],
                        'amount' => $process['amount'],
                        'price' => $process['price']
                    ]);
                    break;
                case "4":
                    LabourProcess::create([
                        'worksheet_id' => $id,
                        'name' => $process['name'],
                        'info' => $process['info'],
                        'maintenance_id' => NULL,
                        'time_span' => $process['time_span'],
                        'price' => $process['price']
                    ]);
                    break;
            }
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
